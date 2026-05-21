# STOMP Consumer Reconnection Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add automatic reconnection to the STOMP consumer so that `scheduled.php` and `mobiled.php` recover from connection drops without manual intervention.

**Architecture:** A `ReconnectingClient` decorator wraps `IClient`, stores subscriptions, and replays them after reconnecting via a jitter-backed retry loop. `Connection` gains a `close` event listener and `onClose()` hook; `IClient`/`Client` gain `onDisconnect()`. `Daemon\Kernel` wires in the decorator transparently so neither daemon script changes. In the failure path, reconnect is rescheduled via a zero-delay timer (not directly from the promise rejection callback) so that `MaxAttemptsExceededException` propagates through the ReactPHP event loop rather than being silently swallowed by the promise chain.

**Tech Stack:** PHP 8.4, ReactPHP Event Loop, React Promises v3, PHPUnit 11

---

## File Map

| Action | Path |
|---|---|
| Modify | `library/Alaress/Async/Stomp/Implementation/Stream/Connection.php` |
| Modify | `library/Alaress/Async/Stomp/IClient.php` |
| Modify | `library/Alaress/Async/Stomp/Implementation/Stream/Client.php` |
| Create | `library/Alaress/Async/Stomp/Implementation/Stream/ReconnectingClient.php` |
| Modify | `library/Schoolbox/Application/Daemon/Kernel.php` |
| Create | `library/Alaress/Async/Stomp/Implementation/Stream/Test/ConnectionTest.php` |
| Create | `library/Alaress/Async/Stomp/Implementation/Stream/Test/ClientTest.php` |
| Create | `library/Alaress/Async/Stomp/Implementation/Stream/Test/ReconnectingClientTest.php` |

---

## Task 1: Add disconnect detection to `Connection`

**Files:**
- Modify: `library/Alaress/Async/Stomp/Implementation/Stream/Connection.php`
- Create: `library/Alaress/Async/Stomp/Implementation/Stream/Test/ConnectionTest.php`

- [ ] **Step 1: Write the failing tests**

Create `library/Alaress/Async/Stomp/Implementation/Stream/Test/ConnectionTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alaress\Async\Stomp\Implementation\Stream\Test;

use Alaress\Async\Stomp\Implementation\Stream\Connection;
use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;
use React\Stream\DuplexStreamInterface;
use React\Stream\ThroughStream;
use Stomp\Protocol\Protocol;
use Stomp\Protocol\Version;
use Stomp\Transport\Parser;

/**
 * Subclass that injects a ThroughStream instead of opening a real socket.
 */
class TestableConnection extends Connection
{
    private ?ThroughStream $nextStream = null;

    public function prepareStream(ThroughStream $stream): void
    {
        $this->nextStream = $stream;
    }

    protected function createStream(string $host, int $port): DuplexStreamInterface
    {
        return $this->nextStream ?? new ThroughStream();
    }
}

class ConnectionTest extends TestCase
{
    private function makeConnection(): TestableConnection
    {
        return new TestableConnection(
            $this->createMock(LoopInterface::class),
            new Protocol(null, Version::VERSION_1_1),
            new Parser(),
            'localhost',
            61613,
            'guest',
            'guest',
            '/'
        );
    }

    public function testOnCloseCallbackFiredWhenStreamCloses(): void
    {
        $conn   = $this->makeConnection();
        $stream = new ThroughStream();
        $conn->prepareStream($stream);

        $fired = false;
        $conn->onClose(function () use (&$fired): void {
            $fired = true;
        });

        $conn->connect();
        $stream->close();

        $this->assertTrue($fired);
    }

    public function testMultipleOnCloseCallbacksAllFire(): void
    {
        $conn   = $this->makeConnection();
        $stream = new ThroughStream();
        $conn->prepareStream($stream);

        $count = 0;
        $conn->onClose(function () use (&$count): void { $count++; });
        $conn->onClose(function () use (&$count): void { $count++; });

        $conn->connect();
        $stream->close();

        $this->assertSame(2, $count);
    }

    public function testConnectionPromiseResetAfterClose(): void
    {
        $conn    = $this->makeConnection();
        $stream1 = new ThroughStream();
        $conn->prepareStream($stream1);

        $promise1 = $conn->connect();

        $stream2 = new ThroughStream();
        $conn->prepareStream($stream2);
        $stream1->close();

        $promise2 = $conn->connect();

        $this->assertNotSame($promise1, $promise2);
    }

    public function testOnCloseNotFiredBeforeStreamCloses(): void
    {
        $conn   = $this->makeConnection();
        $stream = new ThroughStream();
        $conn->prepareStream($stream);

        $fired = false;
        $conn->onClose(function () use (&$fired): void {
            $fired = true;
        });

        $conn->connect();
        // Do NOT close the stream

        $this->assertFalse($fired);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/phpunit library/Alaress/Async/Stomp/Implementation/Stream/Test/ConnectionTest.php --no-coverage
```

If this fails due to environment issues, run inside Docker:
```bash
docker exec schoolbox-cli ./vendor/bin/phpunit library/Alaress/Async/Stomp/Implementation/Stream/Test/ConnectionTest.php --no-coverage
```

Expected: FAIL — `onClose`, `createStream` methods not found.

- [ ] **Step 3: Modify `Connection`**

In `library/Alaress/Async/Stomp/Implementation/Stream/Connection.php`:

**a)** Add `use React\Stream\DuplexStreamInterface;` to the imports.

**b)** Change the `$stream` property type from `Stream` to `DuplexStreamInterface`:

```php
// Before:
/**
 * @var Stream
 */
private $stream;

// After:
private DuplexStreamInterface $stream;
```

**c)** Add the `$onCloseCallbacks` property after `$connectionPromise`:

```php
/**
 * @var callable[]
 */
private array $onCloseCallbacks = [];
```

**d)** Add the `onClose()` method after `__destruct()`:

```php
/**
 * Register a callback to be invoked when the stream closes.
 */
public function onClose(callable $callback): void
{
    $this->onCloseCallbacks[] = $callback;
}
```

**e)** Add the protected `createStream()` method just before `setupStream()`:

```php
/**
 * Creates the underlying duplex stream for the given host and port.
 *
 * Extracted as a protected method so test subclasses can inject a fake
 * stream without opening a real socket.
 *
 * @throws ConnectionException if the socket cannot be opened.
 */
protected function createStream(string $host, int $port): DuplexStreamInterface
{
    $socket = stream_socket_client(
        sprintf('tcp://%s:%d/', $host, $port),
        $errno,
        $errstr
    );
    if (!$socket) {
        throw new ConnectionException($errstr, $errno);
    }
    return new Stream($socket, $this->loop);
}
```

**f)** Replace the full body of `setupStream()` so it calls `createStream()` and registers the `close` listener:

```php
private function setupStream(): void
{
    $this->stream = $this->createStream($this->host, $this->port);

    $this->stream->on('data', function ($data): void {
        $this->parser->addData($data);
        while ($frame = $this->parser->nextFrame()) {
            if ($frame instanceof BaseFrame) {
                switch ($frame->getCommand()) {
                    case 'CONNECTED':
                        $this->stream->emit('connect', [$frame]);
                        break;
                    case 'ERROR':
                        $this->stream->emit('error', [$frame]);
                        break;
                    case 'MESSAGE':
                        $this->stream->emit('message', [$frame]);
                        break;
                }
            }
        }
    });

    $this->stream->once('close', function (): void {
        $this->connectionPromise = null;
        foreach ($this->onCloseCallbacks as $callback) {
            ($callback)();
        }
    });
}
```

- [ ] **Step 4: Run the tests to verify they pass**

```bash
./vendor/bin/phpunit library/Alaress/Async/Stomp/Implementation/Stream/Test/ConnectionTest.php --no-coverage
```

If this fails due to environment issues, run inside Docker:
```bash
docker exec schoolbox-cli ./vendor/bin/phpunit library/Alaress/Async/Stomp/Implementation/Stream/Test/ConnectionTest.php --no-coverage
```

Expected: 4 tests, 4 assertions, OK.

- [ ] **Step 5: Commit**

```bash
git add library/Alaress/Async/Stomp/Implementation/Stream/Connection.php \
        library/Alaress/Async/Stomp/Implementation/Stream/Test/ConnectionTest.php
git commit -m "Add close detection and onClose hook to STOMP Connection [SA-22217]"
```

---

## Task 2: Add `onDisconnect` to `IClient` and `Client`

**Files:**
- Modify: `library/Alaress/Async/Stomp/IClient.php`
- Modify: `library/Alaress/Async/Stomp/Implementation/Stream/Client.php`
- Create: `library/Alaress/Async/Stomp/Implementation/Stream/Test/ClientTest.php`

- [ ] **Step 1: Write the failing test**

Create `library/Alaress/Async/Stomp/Implementation/Stream/Test/ClientTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alaress\Async\Stomp\Implementation\Stream\Test;

use Alaress\Async\Stomp\Implementation\Stream\Client;
use Alaress\Async\Stomp\Implementation\Stream\Connection;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    public function testOnDisconnectDelegatesToConnectionOnClose(): void
    {
        $conn = $this->createMock(Connection::class);
        $cb   = function (): void {};

        $conn->expects($this->once())
            ->method('onClose')
            ->with($cb);

        $client = new Client($conn);
        $client->onDisconnect($cb);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
./vendor/bin/phpunit library/Alaress/Async/Stomp/Implementation/Stream/Test/ClientTest.php --no-coverage
```

If this fails due to environment issues, run inside Docker:
```bash
docker exec schoolbox-cli ./vendor/bin/phpunit library/Alaress/Async/Stomp/Implementation/Stream/Test/ClientTest.php --no-coverage
```

Expected: FAIL — `onDisconnect` not found on `Client`.

- [ ] **Step 3: Add `onDisconnect` to `IClient`**

In `library/Alaress/Async/Stomp/IClient.php`, add this method after `subscribeWithAck`:

```php
/**
 * Registers a callback to be invoked when this connection is lost.
 *
 * @param callable $callback
 * @return void
 */
public function onDisconnect(callable $callback): void;
```

- [ ] **Step 4: Implement `onDisconnect` in `Client`**

In `library/Alaress/Async/Stomp/Implementation/Stream/Client.php`, add this method after `connect()`:

```php
/**
 * @inheritDoc
 */
public function onDisconnect(callable $callback): void
{
    $this->connection->onClose($callback);
}
```

- [ ] **Step 5: Run the test to verify it passes**

```bash
./vendor/bin/phpunit library/Alaress/Async/Stomp/Implementation/Stream/Test/ClientTest.php --no-coverage
```

If this fails due to environment issues, run inside Docker:
```bash
docker exec schoolbox-cli ./vendor/bin/phpunit library/Alaress/Async/Stomp/Implementation/Stream/Test/ClientTest.php --no-coverage
```

Expected: 1 test, 1 assertion, OK.

- [ ] **Step 6: Commit**

```bash
git add library/Alaress/Async/Stomp/IClient.php \
        library/Alaress/Async/Stomp/Implementation/Stream/Client.php \
        library/Alaress/Async/Stomp/Implementation/Stream/Test/ClientTest.php
git commit -m "Add onDisconnect to IClient and Client [SA-22217]"
```

---

## Task 3: Create `ReconnectingClient`

**Files:**
- Create: `library/Alaress/Async/Stomp/Implementation/Stream/ReconnectingClient.php`
- Create: `library/Alaress/Async/Stomp/Implementation/Stream/Test/ReconnectingClientTest.php`

### A note on exception propagation

In the reconnect failure path, `scheduleReconnect()` must not be called directly from within a React Promise `.then()` rejection callback — React Promise v3 catches any exception thrown inside those callbacks and silently rejects the next promise in the chain, so `MaxAttemptsExceededException` would never reach the event loop. Instead, the rejection handler schedules a zero-delay `addTimer` call, and `scheduleReconnect()` is called from that timer callback — exceptions thrown there propagate through ReactPHP's event loop and kill the daemon as intended.

This means the failure path fires **two timers** per attempt: one for the jitter delay, one for the zero-delay reschedule after a failed connect. The tests account for this.

- [ ] **Step 1: Write the failing tests**

Create `library/Alaress/Async/Stomp/Implementation/Stream/Test/ReconnectingClientTest.php`:

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/phpunit library/Alaress/Async/Stomp/Implementation/Stream/Test/ReconnectingClientTest.php --no-coverage
```

If this fails due to environment issues, run inside Docker:
```bash
docker exec schoolbox-cli ./vendor/bin/phpunit library/Alaress/Async/Stomp/Implementation/Stream/Test/ReconnectingClientTest.php --no-coverage
```

Expected: FAIL — `ReconnectingClient` class not found.

- [ ] **Step 3: Create `ReconnectingClient`**

Create `library/Alaress/Async/Stomp/Implementation/Stream/ReconnectingClient.php`:

```php
<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp\Implementation\Stream;

use Alaress\Async\Stomp\IClient;
use Alaress\Jitter\IJitter;
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
     */
    private array $subscriptions = [];

    private int $reconnectAttempts = 0;

    /** @var callable[] */
    private array $externalDisconnectHandlers = [];

    /**
     * @param \Closure(): IClient  $factory  Creates a fresh IClient on each connect attempt.
     * @param LoopInterface        $loop
     * @param IJitter              $jitter
     * @param LoggerInterface|null $log      Optional; logging can be wired in later.
     */
    public function __construct(
        private \Closure $factory,
        private LoopInterface $loop,
        private IJitter $jitter,
        private ?LoggerInterface $log = null,
    ) {
        $this->inner = ($this->factory)();
    }

    /**
     * @inheritDoc
     */
    public function connect(): PromiseInterface
    {
        $deferred = new Deferred();
        $this->inner->connect()->then(
            function () use ($deferred): void {
                $this->inner->onDisconnect(function (): void {
                    $this->scheduleReconnect();
                });
                $deferred->resolve($this);
            },
            function ($error) use ($deferred): void {
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
            $this->inner = ($this->factory)();
            $this->inner->connect()->then(
                function (): void {
                    foreach ($this->subscriptions as [$dest, $ackMode, $cb, $headers]) {
                        $this->inner->subscribeWithAck($dest, $ackMode, $cb, $headers);
                    }
                    $this->inner->onDisconnect(function (): void {
                        $this->scheduleReconnect();
                    });
                    $this->reconnectAttempts = 0;
                    foreach ($this->externalDisconnectHandlers as $handler) {
                        ($handler)();
                    }
                },
                function (): void {
                    $this->reconnectAttempts++;
                    // Use addTimer(0) rather than calling scheduleReconnect() directly here.
                    // React Promise v3 catches exceptions thrown inside .then() callbacks,
                    // so MaxAttemptsExceededException would be silently swallowed if thrown
                    // here. The zero-delay timer escapes the promise chain.
                    $this->loop->addTimer(0, function (): void {
                        $this->scheduleReconnect();
                    });
                }
            );
        });
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

```bash
./vendor/bin/phpunit library/Alaress/Async/Stomp/Implementation/Stream/Test/ReconnectingClientTest.php --no-coverage
```

If this fails due to environment issues, run inside Docker:
```bash
docker exec schoolbox-cli ./vendor/bin/phpunit library/Alaress/Async/Stomp/Implementation/Stream/Test/ReconnectingClientTest.php --no-coverage
```

Expected: 9 tests, OK.

- [ ] **Step 5: Commit**

```bash
git add library/Alaress/Async/Stomp/Implementation/Stream/ReconnectingClient.php \
        library/Alaress/Async/Stomp/Implementation/Stream/Test/ReconnectingClientTest.php
git commit -m "Add ReconnectingClient STOMP decorator [SA-22217]"
```

---

## Task 4: Wire `ReconnectingClient` into `Daemon\Kernel`

**Files:**
- Modify: `library/Schoolbox/Application/Daemon/Kernel.php`

- [ ] **Step 1: Add imports to `Daemon\Kernel`**

In `library/Schoolbox/Application/Daemon/Kernel.php`, add to the `use` block at the top:

```php
use Alaress\Jitter\Jitter;
use Alaress\Random\FloatGenerator;
```

- [ ] **Step 2: Replace the `stomp` DI entry**

Find the existing `stomp` DI entry (currently around line 419):

```php
$di->set('stomp', $di->lazy(function () use ($di) {
    /** @var IConfigLoader $conf */
    $conf = $di->get('config');
    $conn = new Stomp\Connection(
        $di->get('react.loop'),
        new Protocol(null, Version::VERSION_1_1),
        new Parser(),
        $conf->get('amqp_host'),
        (int)$conf->get('amqp_stomp_port'),
        $conf->get('amqp_user'),
        $conf->get('amqp_pass'),
        $conf->get('amqp_vhost')
    );
    return new Stomp\Client($conn);
}));
```

Replace it entirely with:

```php
$di->set('stomp', $di->lazy(function () use ($di) {
    /** @var IConfigLoader $conf */
    $conf = $di->get('config');
    $loop = $di->get('react.loop');

    $factory = function () use ($conf, $loop): Stomp\Client {
        $conn = new Stomp\Connection(
            $loop,
            new Protocol(null, Version::VERSION_1_1),
            new Parser(),
            $conf->get('amqp_host'),
            (int)$conf->get('amqp_stomp_port'),
            $conf->get('amqp_user'),
            $conf->get('amqp_pass'),
            $conf->get('amqp_vhost')
        );
        return new Stomp\Client($conn);
    };

    return new Stomp\ReconnectingClient(
        $factory,
        $loop,
        new Jitter(new FloatGenerator(), maxAttempts: 10, baseTimeout: 5000),
    );
}));
```

- [ ] **Step 3: Run the full STOMP test suite**

```bash
./vendor/bin/phpunit library/Alaress/Async/Stomp/ --no-coverage
```

If this fails due to environment issues, run inside Docker:
```bash
docker exec schoolbox-cli ./vendor/bin/phpunit library/Alaress/Async/Stomp/ --no-coverage
```

Expected: all tests pass.

- [ ] **Step 4: Run the full library test suite to check for regressions**

```bash
./vendor/bin/phpunit --no-coverage
```

If this fails due to environment issues, run inside Docker:
```bash
docker exec schoolbox-cli ./vendor/bin/phpunit --no-coverage
```

Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add library/Schoolbox/Application/Daemon/Kernel.php
git commit -m "Wire ReconnectingClient into Daemon\Kernel for stomp DI entry [SA-22217]"
```
