# Redis Adapter

Both the [cache](../io/redis-cache.md) and [storage](../io/redis-storage.md)
Redis databases are communicated with via an adapter.

> TODO mention how a cache vs. storage adapter is retrieved:
> also talk about how they're fetched from DI

This adapter uses the [Predis](https://github.com/predis/predis) library for
communicating with Redis.

The daemon kernel is able to subscribe asynchronously to a queue in Redis in
order to receive signals: subscription uses the
[asynchronous Predis](https://github.com/laravie/predis-async) library.

In addition, the daemon kernel uses the `\Alaress\Redis\Async\Client` class to
abstract the details of queue subscription.

## Instantiation

The Redis adapter variants may be fetched via the DI container:

```php
/** @var \Psr\Container\ContainerInterface $di */
/** @var \Predis\ClientInterface $redisStorage */
$redisStorage = $di->get('redis.storage');

/** @var \Predis\ClientInterface $redisCache */
$redisCache = $di->get('redis.cache');

/** @var \Predis\ClientInterface $redisSignalQueuePub */
$redisSignalQueuePub = $di->get('redis.signalQueue.pub');

// Available only within the daemon kernel
/** @var \Alaress\Redis\Async\Client $redisSignalQueueSub */
$redisSignalQueueSub = $di->get('redis.signalQueue.sub');
```

Less desirably, synchronous instances of the Redis adapter are accessible as
singletons:

```php
/** @var \Predis\ClientInterface $redisStorage */
$redisStorage = \Alaress\Redis::getStorageClientInstance();

/** @var \Predis\ClientInterface $redisCache */
$redisCache = \Alaress\Redis::getCacheClientInstance();

/** @var \Predis\ClientInterface $redisSignalQueuePub */
$redisSignalQueuePub = \Alaress\Redis::getSignalQueueClientInstance();
```

The `\Alaress\Redis` class also allows access to wrapped versions of these
instances, which allow function calls to be cached:

```php
$redisWrapper = \Alaress\Redis::getCacheInstance();
$redisCache   = $redisWrapper->getClient();
```

## Usage

The Redis adapters have access to all Predis methods:

```php
/** @var \Predis\ClientInterface $redisStorage */

// Setting a value
$redisStorage->set($key, $value);

// Getting a value
$value = $redisStorage->get($key);

// Iterating a set of keys which match a filter
$keyspace = new \Predis\Collection\Iterator\Keyspace($redisStorage, "*");
foreach ($keyspace as $key) {
    ...
}

// Transactions
$responses = $redisStorage->transaction(
    function (\Predis\Transaction\MultiExec $tx) {
        ...
    }
);
```

The queue subscription allows access to Redis'
[pub-sub commands](https://redis.io/commands/?group=pubsub):

```php
$eventListener = new class implements \Alaress\Redis\Async\EventListener {...}

/** @var \Alaress\Redis\Async\Client $redisSignalQueueSub */
$redisSignalQueueSub->subscribe($channel, $eventListener);
$redisSignalQueueSub->unsubscribe($channel);
$redisSignalQueueSub->psubscribe($pattern, $eventListener);
$redisSignalQueueSub->punsubscribe($pattern);
```

Function call caches use the `\Alaress\Redis` wrapper:

```php
$redisWrapper = \Alaress\Redis::getCacheInstance();
$result       = $redisWrapper->cacheFunctionCall(
    $key,
    $callback,
    $params,
    $timeout,
);
```
