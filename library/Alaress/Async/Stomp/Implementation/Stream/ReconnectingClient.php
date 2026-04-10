<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp\Implementation\Stream;

use Alaress\Async\Stomp\IClient;
use Alaress\Jitter\IJitter;
use Closure;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

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
    }

    /**
     * @inheritDoc
     */
    public function connect(): PromiseInterface
    {
        $deferred = new Deferred();
        $this->log->debug("Connecting to STOMP client...");
        $this->inner->connect()->then(
            function () use ($deferred): void {
                $this->log->debug("Connected to STOMP client");
                $this->inner->onDisconnect($this->scheduleReconnect(...));
                $deferred->resolve($this);
            },
            function ($error) use ($deferred): void {
                $this->log->warning("Connected to STOMP client");
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
     * @inheritDoc
     *
     * Registered callbacks fire after a successful reconnect and subscription replay.
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
     */
    private function scheduleReconnect(): void
    {
        // May throw MaxAttemptsExceededException — intentionally uncaught.
        $delay = $this->jitter->calculateJitter($this->reconnectAttempts);

        $this->loop->addTimer($delay / 1000, function (): void {
            $this->inner = call_user_func($this->factory);
            $this->inner->connect()->then(
                function (): void {
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
                    // Use addTimer(0) rather than calling scheduleReconnect() directly here.
                    // React Promise v3 catches exceptions thrown inside .then() callbacks,
                    // so MaxAttemptsExceededException would be silently swallowed if thrown
                    // here. The zero-delay timer escapes the promise chain.
                    $this->loop->addTimer(0, $this->scheduleReconnect(...));
                }
            );
        });
    }
}
