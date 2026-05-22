<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp\Implementation\Stream;

use Alaress\Async\Stomp\IClient;
use Alaress\Jitter\IJitter;
use Alaress\Jitter\MaxAttemptsExceededException;
use Closure;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Throwable;
use WeakReference;

/**
 * Decorator around IClient that transparently reconnects and replays
 * subscriptions when the underlying STOMP connection drops.
 *
 * On exhausting reconnect attempts, MaxAttemptsExceededException propagates
 * through the ReactPHP event loop, killing the daemon so systemd can restart it.
 *
 * Important: the failure-path reschedule is wrapped in a zero-delay addTimer
 * call to escape React Promise's exception handling — exceptions thrown from
 * within .then() callbacks are caught by the promise chain and silently dropped.
 */
class ReconnectingClient implements IClient
{
    private IClient $inner;

    /**
     * Stored subscriptions: each entry is [destination, ackMode, callback, headers].
     *
     * @var array<int, array{string, string, callable, array}>
     * @noinspection PhpPropertyOnlyWrittenInspection - this is read in `scheduleReconnect`
     */
    private array $subscriptions = [];

    /**
     * How many times has this client attempted to reconnect?
     */
    private int $reconnectAttempts = 0;

    /**
     * Pending reconnect timer, if any.
     */
    private ?TimerInterface $pendingTimer = null;

    /**
     * Whether this client has been stopped and should no longer attempt reconnects.
     *
     * Set during PHP shutdown (before GC/destructors) to prevent scheduleReconnect()
     * from registering a new ev_timer on the stopped loop — which would leave a
     * dangling C watcher that segfaults when ev_loop_destroy() runs.
     */
    private bool $stopped = false;

    /**
     * @var array<callable>
     * @noinspection PhpPropertyOnlyWrittenInspection - this is read in `scheduleReconnect`
     */
    private array $externalDisconnectHandlers = [];

    /**
     * @param Closure(): IClient   $factory  Creates a fresh IClient on each connect attempt.
     * @param LoopInterface        $loop
     * @param IJitter              $jitter
     * @param LoggerInterface|null $log      Optional; logging can be wired in later.
     */
    public function __construct(
        private Closure $factory,
        private LoopInterface $loop,
        private IJitter $jitter,
        private ?LoggerInterface $log = null,
    ) {
        $this->inner = call_user_func($this->factory);

        // PHP shutdown functions run before GC/destructors.
        // Stopping on shutdown prevents scheduleReconnect() from registering an
        // ev_timer on the dead loop when Connection::__destruct() fires the
        // onClose callback during teardown.
        // It's implemented as a weak reference because otherwise `$this` will
        // not be released if the object is destructed before shutdown, thus
        // holding this object in memory indefinitely.
        $weakRef = WeakReference::create($this);
        register_shutdown_function(fn () => $weakRef->get()?->stop());
    }

    /**
     * Cancel any pending reconnect timer and prevent further reconnect attempts.
     *
     * Safe to call on a stopped loop — cancelTimer() only operates on live C watchers
     * if the ev_loop is still alive, which it is at shutdown-function time.
     */
    private function stop(): void
    {
        $this->stopped = true;
        if ($this->pendingTimer) {
            $this->log?->debug("Shutdown received: stopping reconnection timer function");
            $this->loop->cancelTimer($this->pendingTimer);
            $this->pendingTimer = null;
        } else {
            $this->log?->debug("Shutdown received, but no reconnection timer function to stop");
        }
    }

    /**
     * @inheritDoc
     */
    public function connect(): PromiseInterface
    {
        $deferred = new Deferred();
        $this->log?->debug("Connecting to STOMP queue...");
        $this->inner->connect()->then(
            function () use ($deferred): void {
                $this->log?->debug("Connected to STOMP queue");
                $this->inner->onDisconnect($this->scheduleReconnect(...));
                $deferred->resolve($this);
            },
            function ($error) use ($deferred): void {
                $this->log?->warning("Connection to STOMP queue failed", ['exception' => $error]);
                $deferred->reject($error);
            }
        );
        return $deferred->promise();
    }

    /**
     * @inheritDoc
     */
    public function subscribeWithAck(string $destination, string $ackMode, callable $callback, array $headers = [])
    {
        $this->subscriptions[] = [$destination, $ackMode, $callback, $headers];
        return $this->inner->subscribeWithAck($destination, $ackMode, $callback, $headers);
    }

    /**
     * Registers a callback to be invoked after a successful reconnect and subscription replay.
     *
     * Note: unlike a bare Client, this implementation does NOT fire the callback immediately
     * on disconnect — it fires after the connection has been re-established and subscriptions
     * replayed. This allows callers to re-register any connection-dependent state only once
     * a usable connection is available again.
     *
     * If the maximum number of reconnect attempts is exhausted and the daemon exits,
     * the callback will not fire.
     */
    public function onDisconnect(callable $callback): void
    {
        $this->externalDisconnectHandlers[] = $callback;
    }

    /**
     * Schedule a reconnect attempt via the event loop, backing off with jitter.
     *
     * Throws MaxAttemptsExceededException (via Jitter::calculateJitter) when
     * attempts are exhausted. When called from a timer callback this exception
     * propagates through the ReactPHP event loop and kills the daemon.
     *
     * @throws MaxAttemptsExceededException if max number of attempts is passed.
     */
    private function scheduleReconnect(): void
    {
        if ($this->stopped) {
            return;
        }

        $delay = $this->jitter->calculateJitter($this->reconnectAttempts);
        $this->log?->info(
            "Disconnected from STOMP queue: attempting to reconnect",
            [
                'attempts' => $this->reconnectAttempts,
                'delay'    => $delay / 1000,
            ],
        );

        $this->pendingTimer = $this->loop->addTimer(
            $delay / 1000,
            function (): void {
                $this->pendingTimer = null;
                if ($this->stopped) {
                    return;
                }
                $this->log?->debug("Reconnecting to STOMP queue...");
                try {
                    $this->inner = call_user_func($this->factory);
                } catch (Throwable $e) {
                    $this->log?->warning("Reconnection to STOMP queue failed", ['exception' => $e]);
                    $this->reconnectAttempts++;
                    $this->loop->addTimer(0, $this->scheduleReconnect(...));
                    return;
                }
                $this->inner->connect()->then(
                    function (): void {
                        $this->log?->debug("Reconnected to STOMP queue: resubscribing to subscriptions");
                        foreach ($this->subscriptions as [$dest, $ackMode, $cb, $headers]) {
                            $this->inner->subscribeWithAck($dest, $ackMode, $cb, $headers);
                        }
                        $this->inner->onDisconnect($this->scheduleReconnect(...));
                        $this->reconnectAttempts = 0;
                        foreach ($this->externalDisconnectHandlers as $handler) {
                            call_user_func($handler);
                        }
                    },
                    function (): void {
                        $this->reconnectAttempts++;
                        $this->log?->warning(
                            "Reconnection to STOMP queue failed",
                            ['attempts' => $this->reconnectAttempts,],
                        );
                        // Use addTimer(0) rather than calling scheduleReconnect() directly here.
                        // React Promise v3 catches exceptions thrown inside .then() callbacks,
                        // so MaxAttemptsExceededException would be silently swallowed if thrown
                        // here. The zero-delay timer escapes the promise chain.
                        $this->loop->addTimer(0, $this->scheduleReconnect(...));
                    }
                );
            }
        );
    }
}
