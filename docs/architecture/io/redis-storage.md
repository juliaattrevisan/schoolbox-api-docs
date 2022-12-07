# Redis Storage

Schoolbox uses a Redis instance as a key-value store, primarily for storing
unstructured data.

The Redis instance the storage system uses trades off performance for
persistence: data written to the instance is persisted to disk on write, thus
guaranteeing that the data is persisted for as long as requested.

> See [Redis Cache](redis-cache.md) for a Redis instance which makes the
> opposite tradeoff.

The storage system may store the following data:
- Sessions
- Notification subsystem data
- Access tokens for LTI tools

Communication with the Redis store is done via the
[Redis adapter](../adapters/redis.md).
