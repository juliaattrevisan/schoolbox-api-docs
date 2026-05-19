<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp\Implementation\Stream\Test;

use Alaress\Async\Stomp\IClient;
use Alaress\Async\Stomp\Implementation\Stream\ReconnectingClient;
use Alaress\Jitter\IJitter;
use Alaress\Jitter\MaxAttemptsExceededException;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Promise\Deferred;

use function React\Promise\resolve;

#[CoversMethod(ReconnectingClient::class, 'connect')]
#[CoversMethod(ReconnectingClient::class, 'onDisconnect')]
#[CoversMethod(ReconnectingClient::class, 'stop')]
class ReconnectingClientTest extends TestCase
{
    /**
     * Build a mock IClient whose connect() resolves immediately and whose
     * onDisconnect() captures the registered callback for later manual firing.
     *
     * @param callable|null &$captureDisconnect A reference to a method which will be called on disconnect.
     * @return IClient&MockObject
     */
    private function makeInnerClient(?callable &$captureDisconnect = null): IClient&MockObject
    {
        $client = $this->createMock(IClient::class);
        /** @noinspection PhpParamsInspection - null is a valid parameter to resolve() */
        $client->method('connect')->willReturn(resolve(null));
        $client->method('onDisconnect')
            ->willReturnCallback(function (callable $cb) use (&$captureDisconnect): void {
                $captureDisconnect = $cb;
            });
        return $client;
    }

    /**
     * Build a mock LoopInterface that records addTimer calls.
     *
     * @param array<array{0: float, 1: callable}> &$timers A reference to any timers which get added via `addTimer`.
     *                                                     Call $timers[N][1]() to fire a timer manually in tests.
     */
    private function makeLoop(array &$timers): LoopInterface
    {
        $loop = $this->createMock(LoopInterface::class);
        $loop->method('addTimer')
            ->willReturnCallback(function (float $delay, callable $cb) use (&$timers): TimerInterface {
                $timers[] = [$delay, $cb];
                return $this->createMock(TimerInterface::class);
            });
        return $loop;
    }

    /**
     * Test that `connect` calls the client's `connect` method.
     */
    public function testConnectDelegatesToInnerClient(): void
    {
        $inner = $this->createMock(IClient::class);
        /** @noinspection PhpParamsInspection - null is a valid parameter to resolve() */
        $inner->expects($this->once())->method('connect')->willReturn(resolve(null));
        $inner->method('onDisconnect');

        $client = new ReconnectingClient(
            fn () => $inner,
            $this->createMock(LoopInterface::class),
            $this->createMock(IJitter::class),
        );

        $client->connect();
    }

    /**
     * Test that `subscribeWithAck` calls the client's `subscribeWithAck`
     * method.
     */
    public function testSubscribeWithAckDelegatesToInnerClient(): void
    {
        $inner = $this->createMock(IClient::class);
        /** @noinspection PhpParamsInspection - null is a valid parameter to resolve() */
        $inner->method('connect')->willReturn(resolve(null));
        $inner->method('onDisconnect');
        $inner->expects($this->once())
            ->method('subscribeWithAck')
            ->with('queue.test', IClient::ACK_MODE_CLIENT_INDIVIDUAL, $this->isCallable());

        $client = new ReconnectingClient(
            fn () => $inner,
            $this->createMock(LoopInterface::class),
            $this->createMock(IJitter::class),
        );

        $client->connect();
        $client->subscribeWithAck('queue.test', IClient::ACK_MODE_CLIENT_INDIVIDUAL, fn () => null);
    }

    /**
     * Test that a client's `connect` method returns a rejected promise when the
     * internal connection is rejected.
     */
    public function testConnectRejectsWhenInnerClientRejects(): void
    {
        $rejectDeferred = new Deferred();
        $rejectDeferred->reject(new \RuntimeException('initial connect failed'));

        $inner = $this->createMock(IClient::class);
        $inner->method('connect')->willReturn($rejectDeferred->promise());

        $client = new ReconnectingClient(
            fn () => $inner,
            $this->createMock(LoopInterface::class),
            $this->createMock(IJitter::class),
        );

        $rejectedWith = null;
        $client->connect()->then(null, function ($error) use (&$rejectedWith): void {
            $rejectedWith = $error;
        });

        $this->assertInstanceOf(\RuntimeException::class, $rejectedWith);
        $this->assertSame('initial connect failed', $rejectedWith->getMessage());
    }

    /**
     * Test that the client's `connect` method's promise resolves with an
     * instance of itself.
     */
    public function testConnectResolvesWithReconnectingClientInstance(): void
    {
        $inner  = $this->makeInnerClient();
        $client = new ReconnectingClient(
            fn () => $inner,
            $this->createMock(LoopInterface::class),
            $this->createMock(IJitter::class),
        );

        $resolved = null;
        $client->connect()->then(function ($value) use (&$resolved): void {
            $resolved = $value;
        });

        $this->assertSame($client, $resolved);
    }

    /**
     * Test that a timer method is added when a disconnect is triggered:
     * presumably, this timer will attempt to reconnect.
     */
    public function testDisconnectTriggersTimerViaLoop(): void
    {
        $timers         = [];
        $disconnectHook = fn () => null;

        $inner  = $this->makeInnerClient($disconnectHook);
        $loop   = $this->makeLoop($timers);
        $jitter = $this->createMock(IJitter::class);
        $jitter->method('calculateJitter')->willReturn(0.0);

        $client = new ReconnectingClient(fn () => $inner, $loop, $jitter);
        $client->connect();

        $this->assertNotNull($disconnectHook, 'onDisconnect should have been registered after connect()');
        $disconnectHook();

        $this->assertCount(1, $timers, 'addTimer should be called once on disconnect');
    }

    /**
     * Test that a connection re-subscribes to its queues on reconnect.
     */
    public function testSubscriptionsReplayedOnReconnect(): void
    {
        $timers         = [];
        $disconnectHook = fn () => null;

        $firstInner  = $this->makeInnerClient($disconnectHook);
        $secondInner = $this->makeInnerClient();
        $secondInner->expects($this->once())
            ->method('subscribeWithAck')
            ->with('sb.schedule', IClient::ACK_MODE_CLIENT_INDIVIDUAL, $this->isType('callable'));

        $callCount = 0;
        $clients   = [$firstInner, $secondInner];
        $factory   = function () use (&$callCount, $clients): IClient {
            return $clients[$callCount++];
        };

        $loop   = $this->makeLoop($timers);
        $jitter = $this->createMock(IJitter::class);
        $jitter->method('calculateJitter')->willReturn(0.0);

        $client = new ReconnectingClient($factory, $loop, $jitter);
        $client->connect();
        $client->subscribeWithAck('sb.schedule', IClient::ACK_MODE_CLIENT_INDIVIDUAL, fn () => null);

        $disconnectHook();  // simulate drop → scheduleReconnect → addTimer(fn1)
        $timers[0][1]();    // fire fn1 → connect succeeds → replay subscriptions
    }


    /**
     * Test that a connection's attempt counter resets to zero after it has
     * successfully reconnected.
     */
    public function testAttemptCounterResetsAfterSuccessfulReconnect(): void
    {
        $timers         = [];
        $disconnectHook = fn () => null;

        $firstInner  = $this->makeInnerClient($disconnectHook);
        $secondInner = $this->makeInnerClient();

        $callCount = 0;
        $clients   = [$firstInner, $secondInner];
        $factory   = function () use (&$callCount, $clients): IClient {
            return $clients[$callCount++];
        };

        $loop = $this->makeLoop($timers);

        $capturedAttempts = [];
        $jitter           = $this->createMock(IJitter::class);
        $jitter->method('calculateJitter')
            ->willReturnCallback(function (int $attempts) use (&$capturedAttempts): float {
                $capturedAttempts[] = $attempts;
                return 0.0;
            });

        $client = new ReconnectingClient($factory, $loop, $jitter);
        $client->connect();

        $disconnectHook();  // drop → scheduleReconnect(0) → calculateJitter(0) → addTimer(fn1)
        $timers[0][1]();    // fn1 fires → connect succeeds → reconnectAttempts reset to 0

        // Jitter was called with attempt=0 for the first reconnect
        $this->assertSame([0], $capturedAttempts);
    }

    /**
     * Test that a client's disconnect callback fires upon its disconnection.
     */
    public function testOnDisconnectExternalHandlerFiredAfterReconnect(): void
    {
        $timers         = [];
        $disconnectHook = fn () => null;

        $firstInner  = $this->makeInnerClient($disconnectHook);
        $secondInner = $this->makeInnerClient();

        $callCount = 0;
        $clients   = [$firstInner, $secondInner];
        $factory   = function () use (&$callCount, $clients): IClient {
            return $clients[$callCount++];
        };

        $loop   = $this->makeLoop($timers);
        $jitter = $this->createMock(IJitter::class);
        $jitter->method('calculateJitter')->willReturn(0.0);

        $client = new ReconnectingClient($factory, $loop, $jitter);
        $client->connect();

        $externalFired = false;
        $client->onDisconnect(function () use (&$externalFired): void {
            $externalFired = true;
        });

        $disconnectHook();  // drop
        $timers[0][1]();    // reconnect succeeds → external handler fires

        $this->assertTrue($externalFired);
    }

    /**
     * Test that a failed reconnection attempt increments the attempt counter.
     *
     * Note that the failure path tests have two timers per failed attempt:
     * * timers[N]:   the jitter-delay timer for the connect attempt itself
     * * timers[N+1]: the zero-delay reschedule timer added by the rejection handler
     *
     * This is necessary because React Promise v3 catches exceptions thrown
     * inside .then() callbacks; using addTimer(0) escapes the promise chain
     * so MaxAttemptsExceededException propagates through the event loop.
     */
    public function testFailedReconnectIncrementsAttemptCounter(): void
    {
        $timers         = [];
        $disconnectHook = fn () => null;

        $firstInner = $this->makeInnerClient($disconnectHook);

        $rejectDeferred = new Deferred();
        $rejectDeferred->reject(new \RuntimeException('connect failed'));
        $secondInner = $this->createMock(IClient::class);
        $secondInner->method('connect')->willReturn($rejectDeferred->promise());

        $callCount = 0;
        $clients   = [$firstInner, $secondInner];
        $factory   = function () use (&$callCount, $clients, $secondInner): IClient {
            return $clients[$callCount++] ?? $secondInner;
        };

        $capturedAttempts = [];
        $jitter           = $this->createMock(IJitter::class);
        $jitter->method('calculateJitter')
            ->willReturnCallback(function (int $attempts) use (&$capturedAttempts): float {
                $capturedAttempts[] = $attempts;
                return 0.0;
            });

        $loop   = $this->makeLoop($timers);
        $client = new ReconnectingClient($factory, $loop, $jitter);
        $client->connect();

        $disconnectHook();  // drop → scheduleReconnect(0) → calculateJitter(0) → addTimer(fn1)
        $timers[0][1]();    // fn1: connect fails → rejection → reconnectAttempts=1 → addTimer(0, fn2)
        $timers[1][1]();    // fn2: scheduleReconnect(1) → calculateJitter(1) → addTimer(fn3)

        $this->assertSame([0, 1], $capturedAttempts);
    }

    /**
     * Test that if an exception was thrown in the client factory when
     * reconnecting, that the attempt counter is incremented.
     */
    public function testFactoryThrowInTimerReschedulesAndIncrementsCounter(): void
    {
        $timers         = [];
        $disconnectHook = fn () => null;

        $firstInner = $this->makeInnerClient($disconnectHook);

        $callCount = 0;
        $factory   = function () use (&$callCount, $firstInner): IClient {
            $callCount++;
            if ($callCount > 1) {
                throw new \RuntimeException('factory failed');
            }
            return $firstInner;
        };

        $capturedAttempts = [];
        $jitter           = $this->createMock(IJitter::class);
        $jitter->method('calculateJitter')
            ->willReturnCallback(function (int $attempts) use (&$capturedAttempts): float {
                $capturedAttempts[] = $attempts;
                return 0.0;
            });

        $loop   = $this->makeLoop($timers);
        $client = new ReconnectingClient($factory, $loop, $jitter);
        $client->connect();

        $disconnectHook();  // drop → scheduleReconnect(0) → calculateJitter(0) → addTimer(fn1)
        $timers[0][1]();    // fn1: factory throws → reconnectAttempts=1 → addTimer(0, fn2)

        // The zero-delay reschedule timer must have been enqueued
        $this->assertCount(2, $timers, 'A zero-delay reschedule timer should be enqueued after factory failure');
        // Attempt counter was incremented
        $this->assertSame([0], $capturedAttempts);

        // Firing fn2 should call calculateJitter(1)
        $timers[1][1]();    // fn2: scheduleReconnect(1) → calculateJitter(1) → addTimer(fn3)
        $this->assertSame([0, 1], $capturedAttempts);
    }

    /**
     * Test that if the maximum number of attempts to reconnect has been
     * reached, that a `MaxAttemptsExceededException` is thrown.
     */
    public function testMaxAttemptsExceptionThrownWhenExhausted(): void
    {
        $timers         = [];
        $disconnectHook = fn () => null;

        $firstInner = $this->makeInnerClient($disconnectHook);

        $rejectDeferred = new Deferred();
        $rejectDeferred->reject(new \RuntimeException('connect failed'));
        $secondInner = $this->createMock(IClient::class);
        $secondInner->method('connect')->willReturn($rejectDeferred->promise());

        $callCount = 0;
        $clients   = [$firstInner, $secondInner];
        $factory   = function () use (&$callCount, $clients, $secondInner): IClient {
            return $clients[$callCount++] ?? $secondInner;
        };

        $call   = 0;
        $jitter = $this->createMock(IJitter::class);
        $jitter->method('calculateJitter')
            ->willReturnCallback(function () use (&$call): float {
                return match ($call++) {
                    /** @phpstan-ignore-next-line - this value may be zero */
                    0       => 0.0,
                    default => throw new MaxAttemptsExceededException('max attempts exceeded'),
                };
            });

        $loop   = $this->makeLoop($timers);
        $client = new ReconnectingClient($factory, $loop, $jitter);
        $client->connect();

        $disconnectHook();  // drop → scheduleReconnect(0): jitter returns 0 → addTimer(fn1)
        $timers[0][1]();    // fn1: connect fails → rejection → reconnectAttempts=1 → addTimer(0, fn2)

        $this->expectException(MaxAttemptsExceededException::class);
        $timers[1][1]();    // fn2: scheduleReconnect(1) → jitter throws
    }

    /**
     * Test that stop() sets the stopped flag, preventing scheduleReconnect()
     * from adding new timers when a subsequent disconnect fires.
     */
    public function testStopPreventsReconnectTimerOnDisconnect(): void
    {
        $timers         = [];
        $disconnectHook = fn () => null;

        $inner  = $this->makeInnerClient($disconnectHook);
        $loop   = $this->makeLoop($timers);
        $jitter = $this->createMock(IJitter::class);

        $client = new ReconnectingClient(fn () => $inner, $loop, $jitter);
        $client->connect();

        $this->invokeStop($client);

        $disconnectHook();

        $this->assertCount(0, $timers, 'stop() should prevent new reconnect timers from being scheduled');
    }

    /**
     * Test that stop() cancels the pending reconnect timer when one exists.
     */
    public function testStopCancelsPendingReconnectTimer(): void
    {
        $disconnectHook = fn () => null;
        $inner          = $this->makeInnerClient($disconnectHook);

        $timer = $this->createMock(TimerInterface::class);

        $loop = $this->createMock(LoopInterface::class);
        $loop->method('addTimer')->willReturn($timer);
        $loop->expects($this->once())->method('cancelTimer')->with($timer);

        $jitter = $this->createMock(IJitter::class);
        $jitter->method('calculateJitter')->willReturn(0.0);

        $client = new ReconnectingClient(fn () => $inner, $loop, $jitter);
        $client->connect();

        $disconnectHook();           // scheduleReconnect → addTimer → pendingTimer set
        $this->invokeStop($client);  // should cancelTimer($timer)
    }

    /**
     * Test that stop() does not call cancelTimer when no reconnect timer is pending.
     */
    public function testStopDoesNotCancelTimerWhenNoneIsPending(): void
    {
        $inner = $this->makeInnerClient();

        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->never())->method('cancelTimer');

        $client = new ReconnectingClient(fn () => $inner, $loop, $this->createMock(IJitter::class));

        $this->invokeStop($client);
    }

    private function invokeStop(ReconnectingClient $client): void
    {
        $method = (new \ReflectionClass($client))->getMethod('stop');
        $method->setAccessible(true);
        $method->invoke($client);
    }
}
