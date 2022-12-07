# Redis Adapter

Both the [cache](../io/redis-cache.md) and [storage](../io/redis-storage.md)
Redis databases are communicated with via an adapter.

This adapter uses the [Predis](https://github.com/predis/predis) library for
communicating with Redis.

The daemon kernel is able to subscribe asynchronously to a queue in Redis in
order to receive signals: this uses the
[asynchronous Predis](https://github.com/laravie/predis-async) library for
subscribing to this queue.

In addition, the daemon kernel uses the `\Alaress\Redis\Async\Client` class to
abstract the details of queue subscription.
