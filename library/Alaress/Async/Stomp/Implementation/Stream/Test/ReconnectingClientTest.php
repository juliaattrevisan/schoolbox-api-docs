<?php

declare(strict_types=1);

namespace Alaress\Async\Stomp\Implementation\Stream\Test;

use Alaress\Async\Stomp\IClient;
use Alaress\Async\Stomp\Implementation\Stream\ReconnectingClient;
use Alaress\Jitter\IJitter;
use Alaress\Jitter\MaxAttemptsExceededException;
use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Promise\Deferred;

use function React\Promise\resolve;

class ReconnectingClientTest extends TestCase
{
    /**
     * Build a mock IClient whose connect() resolves immediately and whose
     * onDisconnect() captures the registered callback for later manual firing.
     *
     * Pass a variable by reference as $captureDisconnect to receive the handler.
     */
    private function makeInnerClient(?callable &$captureDisconnect = null): IClient
    {
        $client = $this->createMock(IClient::class);
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
     * Each entry in $timers is [float $delay, callable $callback].
     * Invoke $timers[N][1]() to fire a timer manually in tests.
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

    // -------------------------------------------------------------------------
    // Basic delegation
    // -------------------------------------------------------------------------

    public function testConnectDelegatesToInnerClient(): void
    {
        $inner = $this->createMock(IClient::class);
        $inner->expects($this->once())->method('connect')->willReturn(resolve(null));
        $inner->method('onDisconnect');

        $client = new ReconnectingClient(
            fn () => $inner,
            $this->createMock(LoopInterface::class),
            $this->createMock(IJitter::class),
        );

        $client->connect();
    }

    public function testSubscribeWithAckDelegatesToInnerClient(): void
    {
        $inner = $this->createMock(IClient::class);
        $inner->method('connect')->willReturn(resolve(null));
        $inner->method('onDisconnect');
        $inner->expects($this->once())
            ->method('subscribeWithAck')
            ->with('queue.test', IClient::ACK_MODE_CLIENT_INDIVIDUAL, $this->isType('callable'));

        $client = new ReconnectingClient(
            fn () => $inner,
            $this->createMock(LoopInterface::class),
            $this->createMock(IJitter::class),
        );

        $client->connect();
        $client->subscribeWithAck('queue.test', IClient::ACK_MODE_CLIENT_INDIVIDUAL, fn () => null);
    }

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

    // -------------------------------------------------------------------------
    // Reconnect loop — success path
    // -------------------------------------------------------------------------

    public function testDisconnectTriggersTimerViaLoop(): void
    {
        $timers         = [];
        $disconnectHook = null;

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

    public function testSubscriptionsReplayedOnReconnect(): void
    {
        $timers         = [];
        $disconnectHook = null;

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

    public function testAttemptCounterResetsAfterSuccessfulReconnect(): void
    {
        $timers         = [];
        $disconnectHook = null;

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

    public function testOnDisconnectExternalHandlerFiredAfterReconnect(): void
    {
        $timers         = [];
        $disconnectHook = null;

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

    // -------------------------------------------------------------------------
    // Reconnect loop — failure path
    //
    // The failure path fires TWO timers per failed attempt:
    //   timers[N]   = the jitter-delay timer for the connect attempt itself
    //   timers[N+1] = the zero-delay reschedule timer added by the rejection handler
    //
    // This is necessary because React Promise v3 catches exceptions thrown
    // inside .then() callbacks; using addTimer(0) escapes the promise chain
    // so MaxAttemptsExceededException propagates through the event loop.
    // -------------------------------------------------------------------------

    public function testFailedReconnectIncrementsAttemptCounter(): void
    {
        $timers         = [];
        $disconnectHook = null;

        $firstInner = $this->makeInnerClient($disconnectHook);

        $rejectDeferred = new Deferred();
        $rejectDeferred->reject(new \RuntimeException('connect failed'));
        $secondInner = $this->createMock(IClient::class);
        $secondInner->method('connect')->willReturn($rejectDeferred->promise());

        $callCount = 0;
        $clients   = [$firstInner, $secondInner];
        $factory   = function () use (&$callCount, $clients): IClient {
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

    public function testMaxAttemptsExceptionThrownWhenExhausted(): void
    {
        $timers         = [];
        $disconnectHook = null;

        $firstInner = $this->makeInnerClient($disconnectHook);

        $rejectDeferred = new Deferred();
        $rejectDeferred->reject(new \RuntimeException('connect failed'));
        $secondInner = $this->createMock(IClient::class);
        $secondInner->method('connect')->willReturn($rejectDeferred->promise());

        $callCount = 0;
        $clients   = [$firstInner, $secondInner];
        $factory   = function () use (&$callCount, $clients): IClient {
            return $clients[$callCount++] ?? $secondInner;
        };

        $call   = 0;
        $jitter = $this->createMock(IJitter::class);
        $jitter->method('calculateJitter')
            ->willReturnCallback(function (int $attempts) use (&$call): float {
                return match ($call++) {
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
}
