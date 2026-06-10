<?php

/**
 * A singleton wrapper around a Predis instance.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress;

use Alaress\CacheFactory\Factory;
use Alaress\Redis\AutoRefreshClient;
use Predis;
use Psr\SimpleCache\CacheInterface;

class Redis
{
    /**
     * Identifier for the cache-only Redis instance.
     *
     * This instance is memory-only and thus suitable only as a cache: it does
     * not write to permanent storage. This means it's much faster than the
     * storage instance, though.
     */
    public const INSTANCE_CACHE = 'cache';

    /**
     * Identifier for the storage Redis instance.
     *
     * This instance writes to permanent storage: this means it is suitable for
     * storing persistent data. The process of writing to permanent storage
     * makes this instance slower and less efficient than the cache-only
     * instance, so this should only be used when persistence is required.
     *
     * TODO consider splitting into message and session storage
     */
    public const INSTANCE_STORAGE = 'storage';

    /**
     * Identifier for the signal queue Redis instance.
     *
     * Currently, this instance is the same as the cache-only instance: it has a
     * separate identifier in case it is moved.
     */
    public const INSTANCE_SIGNAL_QUEUE = 'signalQueue';

    /**
     * Prefix all function call Redis keys with this value.
     */
    public const FUNCTION_CALL_CACHE_PREFIX = 'fc';

    /**
     * String with which a compressed payload will be prefixed.
     */
    private const string COMPRESSED_PAYLOAD_PREFIX = 'gz:';

    /**
     * The compression ratio to use for compressing payloads.
     */
    private const int COMPRESSION_RATIO = 9;

    /**
     * @var self
     */
    protected static $instance;

    /**
     * A list of instances.
     *
     * @var array<string, self>
     */
    protected static $instances = [];

    /**
     * Will be null prior to connect() being called
     *
     * @var null|Predis\ClientInterface
     */
    protected $client;

    /**
     * An identifier for the application that is accessing the Redis cache.
     *
     * Currently only used by `$this->cacheFunctionCall()` so that different
     * applications generate unique keys for the same input.
     *
     * @var string
     */
    protected $instanceId = '';

    /**
     * A cache factory for building Redis-backed caches.
     *
     * Created as needed, and only once, by `buildCache`.
     */
    private ?Factory $cacheFactory = null;

    /**
     * Gets an instance of this wrapper.
     *
     * TODO consider replacing with a separate CacheFunctionCall class
     * (The only use of this class is for constructing and holding instances,
     *  and for `cacheFunctionCall`.)
     *
     * @param string $name The name of the instance to retrieve.
     * @return self
     */
    public static function getInstance(string $name): self
    {
        return (self::$instances[$name] ??= new self());
    }

    /**
     * Get the cache instance of this wrapper.
     *
     * @see INSTANCE_CACHE
     *
     * @return self
     */
    public static function getCacheInstance(): self
    {
        return self::getInstance(self::INSTANCE_CACHE);
    }

    /**
     * Get the storage instance of this wrapper.
     *
     * @see INSTANCE_STORAGE
     *
     * @return self
     */
    public static function getStorageInstance(): self
    {
        return self::getInstance(self::INSTANCE_STORAGE);
    }

    /**
     * Get the signal queue instance of this wrapper. (Unused, but provided for
     * completeness.)
     *
     * @see INSTANCE_SIGNAL_QUEUE
     *
     * @return self
     */
    public static function getSignalQueueInstance(): self
    {
        return self::getInstance(self::INSTANCE_SIGNAL_QUEUE);
    }

    /**
     * Aliases the connection `$connectionName` to `$aliasName`.
     *
     * @param string $connectionName
     * @param string $aliasName
     * @return void
     */
    public static function aliasInstance(string $connectionName, string $aliasName)
    {
        self::$instances[$aliasName] = self::$instances[$connectionName];
    }

    /**
     * Gets client within a stored instance of this wrapper.
     *
     * Will return null if connect() has not yet been called on that instance.
     *
     * @param string $name The name of the instance to retrieve.
     * @return null|Predis\ClientInterface
     */
    public static function getClientInstance(string $name): ?Predis\ClientInterface
    {
        return static::getInstance($name)->getClient();
    }

    /**
     * Gets client within the cache instance of this wrapper.
     *
     * @see INSTANCE_CACHE
     *
     * @return null|Predis\ClientInterface
     */
    public static function getCacheClientInstance(): ?Predis\ClientInterface
    {
        return self::getClientInstance(self::INSTANCE_CACHE);
    }

    /**
     * Gets client within the storage instance of this wrapper.
     *
     * @see INSTANCE_STORAGE
     *
     * @return null|Predis\ClientInterface
     */
    public static function getStorageClientInstance(): ?Predis\ClientInterface
    {
        return self::getClientInstance(self::INSTANCE_STORAGE);
    }

    /**
     * Gets client within the signal queue instance of this wrapper.
     *
     * @see INSTANCE_SIGNAL_QUEUE
     *
     * @return null|Predis\ClientInterface
     */
    public static function getSignalQueueClientInstance(): ?Predis\ClientInterface
    {
        return self::getClientInstance(self::INSTANCE_SIGNAL_QUEUE);
    }

    /**
     * Connects this wrapper to Redis.
     *
     * @see Predis\Client::__construct() for parameter documentation.
     *
     * @param  mixed  $parameters
     * @param  mixed  $options
     * @param  string $instanceId
     *
     * @return $this
     */
    public function connect($parameters = null, $options = null, $instanceId = '')
    {
        // tcp://127.0.0.1:6379
        // TODO: it's much better to be set as separate redis parameters in config
        if (is_string($parameters) && parse_url($parameters)) {
            $parameters = parse_url($parameters);
            $parameters['persistent'] = true;
        }
        $this->setClient(new AutoRefreshClient($parameters, $options));
        $this->instanceId = $instanceId;
        return $this;
    }

    /**
     * Will return null if connect() has not yet been called
     *
     * @return Predis\ClientInterface
     */
    public function getClient(): ?Predis\ClientInterface
    {
        return $this->client;
    }

    /**
     * @param  Predis\ClientInterface $client
     *
     * @return $this
     */
    protected function setClient($client)
    {
        $this->client = $client;
        return $this;
    }

    /**
     * Caches results of a function call in Redis.
     *
     * The result is stored in:
     * "fc:$this->instanceId:$base_key:sha256(serialize($params))".
     *
     * @param string   $base_key The base of the key to store the result in.
     * @param callable $callback A callable that returns a value to be cached,
     *                           if there is no value already in the cache.
     * @param mixed    $params   A parameter to pass to $callback. This is also
     *                           used to generate the cache key.
     * @param int      $timeout  Timeout to set on a stored result.
     *                           A null value stores result without a timeout.
     *                           A value of 0 bypasses the cache entirely.
     *
     * @return mixed
     */
    public function cacheFunctionCall($base_key, $callback, $params, $timeout = null)
    {
        // Timeout = 0: bypass cache completely
        if (isset($timeout) && intval($timeout) === 0) {
            return call_user_func($callback, $params);
        }

        $cache        = $this->buildCache($base_key);
        $key          = $this->getKey($params);
        $cachedValue  = $cache->get($key);

        // Was a result returned? If so decode it
        if (!isset($cachedValue)) {
            $returnValue           = null;
            $requiresRecache       = true;
            $requiresRecalculation = true;
        } else {
            [$returnValue, $requiresRecache, $requiresRecalculation] = $this->decodeCachedValue($cachedValue);
        }

        // No result fetched, or non-comprehensible? recalculate
        if ($requiresRecalculation) {
            $returnValue = call_user_func($callback, $params);
        }

        // Recache if result recalculated, or needs to be recached because the
        // fetched result was not compressed
        if ($requiresRecalculation || $requiresRecache) {
            $cachedValue = $this->encodeCalculatedValue($returnValue);
            $cache->set($key, $cachedValue, $timeout);
        }

        return $returnValue;
    }

    /**
     * clears the results of a function call in Redis.
     *
     * clears data created by \Alaress\Redis::cacheFunctionCall
     *
     * @param string   $base_key The base of the key to store the result in.
     * @param mixed    $params   A parameter to pass to $callback. This is also
     *                           used to generate the cache key.
     *
     * @return int (The number of keys that were removed, will be 1 or 0)
     */
    public function dropKeyCache(string $base_key, mixed $params): int
    {
        $cache = $this->buildCache($base_key);
        $key   = $this->getKey($params);
        return $cache->delete($key) ? 1 : 0;
    }

    /**
     * Build the cache key for inserting and returning cached data.
     *
     * @param mixed $params A parameter to pass to $callback. This is also used
     *                      to generate the cache key.
     * @return string
     */
    private function getKey(mixed $params): string
    {
        return hash('sha256', serialize($params));
    }

    /**
     * Decode the cached value into a final value.
     *
     * @param string $cachedValue
     * @return array{0: mixed, 1: bool, 2: bool} 0: the decoded value, or false if it couldn't be decoded
     *                                           1: does the value need to be recached? Always true if it must be
     *                                              recalculated.
     *                                           2: does the value need to be recalculated?
     */
    private function decodeCachedValue(string $cachedValue): array
    {
        $returnValue           = null;
        $requiresRecache       = false;
        $requiresRecalculation = false;

        // Attempt to decompress the result:
        // if it's not compressed, mark it as requiring recache
        // (but not recalculation)
        if (str_starts_with($cachedValue, self::COMPRESSED_PAYLOAD_PREFIX)) {
            $compressedValue = substr($cachedValue, strlen(self::COMPRESSED_PAYLOAD_PREFIX));
            $serializedValue = $this->uncompressValue($compressedValue);
            if ($serializedValue === false) {
                // Could not compress: just return an error state
                return [false, true, true];
            }
            unset($compressedValue);
        } else {
            $serializedValue = $cachedValue;
            $requiresRecache = true;
        }

        // Raw result is no longer needed, thus it can be freed
        unset($cachedValue);

        // short-circuit unserialization if value is legitimately false:
        // otherwise, if it fails to unserialize then recalculate
        if ($serializedValue === 'b:0;') {
            $returnValue = false;
        } else {
            $unserializedValue = unserialize($serializedValue);
            if ($unserializedValue !== false) {
                $returnValue = $unserializedValue;
            } else {
                $requiresRecalculation = true;
            }
        }

        // Can free both serialized and unserialized value
        unset($serializedValue, $unserializedValue);
        return [$returnValue, $requiresRecache, $requiresRecalculation,];
    }

    /**
     * Encode the value once it's been calculated, compressing if possible.
     *
     * @param mixed $calculatedValue The value which was calculated from
     *                               `cacheFunctionCall`.
     * @return string
     */
    private function encodeCalculatedValue(mixed $calculatedValue): string
    {
        $serializedValue = serialize($calculatedValue);
        $cachedValue     = $this->compressValue($serializedValue);
        if ($cachedValue === false) {
            // Compression failed?
            // just store the uncompressed value, I guess
            $cachedValue = $serializedValue;
        } else {
            $cachedValue = self::COMPRESSED_PAYLOAD_PREFIX . $cachedValue;
        }
        return $cachedValue;
    }

    /**
     * Build a cache for the given key.
     *
     * @param string $baseKey
     * @return CacheInterface
     */
    protected function buildCache(string $baseKey): CacheInterface
    {
        $this->cacheFactory ??= new Factory($this->client);
        return $this->cacheFactory->build($baseKey);
    }

    /**
     * Compress `$value` if possible.
     *
     * @param string $value
     * @return string|false The compressed value, or false if failed.
     */
    protected function compressValue(string $value): string|false
    {
        return gzcompress($value, self::COMPRESSION_RATIO);
    }

    /**
     * Uncompress `$value` if possible.
     *
     * @param string $value
     * @return false|string
     */
    protected function uncompressValue(string $value): string|false
    {
        return gzuncompress($value);
    }
}
