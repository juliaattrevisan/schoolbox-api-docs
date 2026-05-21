# STOMP Consumer Reconnection — Design Spec

**Ticket:** SA-22217
**Date:** 2026-04-10

## Problem

`scheduled.php` and `mobiled.php` both consume from RabbitMQ via a STOMP connection. The
consumer is registered once at startup and never reconnected if the connection drops. If the
STOMP TCP socket closes (RabbitMQ restart, network blip, idle timeout), the daemon continues
running but silently stops receiving messages.

Root cause:
- `Connection` does not listen to `close`/`end` events on its `DuplexResourceStream`, so there is
  no signal path for "the socket dropped"
- `Connection` caches `$connectionPromise`, so calling `connect()` again on the same object
  returns the stale resolved promise — it cannot be reused after a drop
- `IClient` has no disconnect hook
- The AMQP heartbeat (`PCNTLHeartbeatSender`) covers only the outbound publish connection, not
  the STOMP consumer socket

## Goals

- Detect STOMP connection drops and automatically reconnect with jitter backoff
- Replay all registered subscriptions after a successful reconnect
- Terminate the daemon (and let systemd restart it) after max reconnect attempts are exhausted
- Fix applies transparently to both `scheduled.php` and `mobiled.php` via DI — neither script
  changes
- Fix lives entirely in the library layer

## Non-goals

- Reconnection for the AMQP publish connection (separate concern, out of scope)
- Modifying `scheduled.php` or `mobiled.php`

---

## Architecture

Five targeted changes:

| File | Change |
|---|---|
| `library/Alaress/Async/Stomp/Implementation/Stream/Connection.php` | Listen for `close`/`end` on stream; expose `onClose(callable)` |
| `library/Alaress/Async/Stomp/IClient.php` | Add `onDisconnect(callable): void` |
| `library/Alaress/Async/Stomp/Implementation/Stream/Client.php` | Implement `onDisconnect` by delegating to `Connection::onClose` |
| `library/Alaress/Async/Stomp/Implementation/Stream/ReconnectingClient.php` (new) | Decorator implementing `IClient`; handles backoff, retry, and subscription replay |
| `library/Schoolbox/Application/Daemon/Kernel.php` | Wrap the `stomp` DI entry in `ReconnectingClient` |

---

## Component Detail

### `Connection`

Add to `setupStream()`: register listeners for both `close` and `end` events on `$this->stream`.
When either fires:
1. Reset `$this->connectionPromise = null` so `connect()` can be called again on a fresh attempt
2. Invoke all registered `onClose` callbacks

Add method:
```php
public function onClose(callable $callback): void
```
Stores `$callback`; all registered callbacks are invoked when the stream closes.

Extract stream creation to a protected method to allow test subclassing:
```php
protected function createStream(string $host, int $port): Stream
```
`setupStream()` calls this instead of constructing `DuplexResourceStream` inline.

### `IClient`

Add one method:
```php
public function onDisconnect(callable $callback): void;
```

### `Client`

Implement `onDisconnect` by delegating to `$this->connection->onClose($callback)`.

### `ReconnectingClient`

```php
class ReconnectingClient implements IClient
```

**Constructor:**
```php
public function __construct(
    private Closure $factory,        // (): IClient — creates a fresh Client+Connection
    private LoopInterface $loop,
    private IJitter $jitter,
    private LoggerInterface $log,
)
```

**State:**
- `$inner: IClient` — the current active client
- `$subscriptions: array` — list of `[destination, ackMode, callback]` registered via `subscribeWithAck`
- `$reconnectAttempts: int` — reset to 0 on successful reconnect

**`connect(): PromiseInterface`**

Delegates to `$this->inner->connect()`. On success, registers `onDisconnect` handler on the
inner client to trigger the reconnect loop.

**`subscribeWithAck(...)`**

Stores `[destination, ackMode, callback]` in `$this->subscriptions`, then delegates to
`$this->inner->subscribeWithAck(...)`.

**`onDisconnect(callable $callback): void`**

Registers a callback that fires after a successful reconnect (i.e. after the full
connect+subscribe sequence completes). Not forwarded to the inner client — `ReconnectingClient`
manages its own disconnect lifecycle.

**Reconnect loop (internal):**

Triggered when the inner client fires its `onDisconnect` callback:

```
function scheduleReconnect():
    try:
        delay = jitter->calculateJitter(reconnectAttempts)
                // throws MaxAttemptsExceededException when exhausted
    catch MaxAttemptsExceededException:
        throw  // daemon exits; systemd restarts

    loop->addTimer(delay / 1000, function():
        inner = factory()
        inner->connect()->then(
            onSuccess: function():
                foreach subscriptions as [dest, ackMode, cb]:
                    inner->subscribeWithAck(dest, ackMode, cb)
                inner->onDisconnect(fn => scheduleReconnect())
                reconnectAttempts = 0
                // fire any registered onDisconnect callbacks (external observers)
            onFailure: function():
                reconnectAttempts++
                scheduleReconnect()
        )
    )
```

Any non-connection error thrown during a reconnect attempt increments the counter and
schedules the next retry rather than propagating immediately.

### `Daemon\Kernel`

The `stomp` DI entry is replaced with a `ReconnectingClient` wrapping the existing
`Client`+`Connection` factory. Default jitter parameters (max attempts, base timeout) are
chosen to allow for several minutes of RabbitMQ downtime before giving up. Exact values can
be tuned via config or adjusted later.

---

## Reconnection Sequence

```
Normal startup:
  Kernel DI resolves 'stomp' as ReconnectingClient
  daemon calls $stomp->connect()->then(fn => registerConsumer(...))
  ReconnectingClient delegates connect() to inner Client
  on success: subscribeWithAck() stores subscription + registers onDisconnect

On drop:
  DuplexResourceStream emits 'close' or 'end'
  Connection resets $connectionPromise, fires onClose callbacks
  Client fires onDisconnect callbacks
  ReconnectingClient schedules reconnect via loop->addTimer(jitter)
    success: replay subscriptions, reset attempt counter
    failure: increment attempts, reschedule
             if attempts >= max: throw → daemon exits → systemd restarts
```

---

## Error Handling

- **Drop during runtime:** handled by reconnect loop as above
- **Initial connect failure:** `connect()` rejects its promise as before; existing
  `catch (ConnectionException)` and `catch (AMQPRuntimeException)` blocks in each daemon
  script are unchanged
- **Max attempts exhausted:** `MaxAttemptsExceededException` propagates out of the
  `addTimer` callback, killing the daemon process; systemd handles restart
- **Non-connection errors during reconnect:** treated as a failed attempt (counter
  incremented) rather than an immediate fatal

---

## Testing

### `Connection`

Extract `createStream()` as a protected method. Test subclass overrides it to return a
`React\Stream\ThroughStream` (in-memory duplex stream, no real socket needed).

Tests:
- Emitting `close` on the injected stream triggers registered `onClose` callbacks
- Emitting `end` on the injected stream triggers registered `onClose` callbacks
- After `close`/`end`, `$connectionPromise` is reset (calling `connect()` again returns a new promise)
- Multiple `onClose` callbacks all fire

### `Client`

- `onDisconnect($cb)` delegates to `Connection::onClose($cb)`

### `ReconnectingClient`

Test doubles:
- **Mock `IClient`** (spy): records calls to `connect()`, `subscribeWithAck()`, `onDisconnect()`;
  exposes `triggerDisconnect()` to fire the registered onDisconnect callback manually
- **Fake `LoopInterface`**: captures `addTimer(delay, callback)` calls; exposes
  `flushTimers()` to invoke them synchronously
- **Mock `IJitter`**: returns predictable delays (e.g. always 0 for most tests)
- **Factory**: `fn(): IClient` returning a fresh mock per call

Tests:
- `connect()` delegates to inner client
- `subscribeWithAck()` delegates to inner client and stores the subscription
- On disconnect, `addTimer` is called with the jitter delay
- After simulated reconnect, all stored subscriptions are replayed on the new inner client
- After simulated reconnect, `onDisconnect` is re-registered on the new inner client
- Attempt counter resets to 0 after successful reconnect
- Failed reconnect increments attempt counter and reschedules
- `MaxAttemptsExceededException` is thrown once attempts are exhausted
