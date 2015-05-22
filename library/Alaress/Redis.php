<?php
/**
 * A singleton wrapper around a Predis instance.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress;

use Alaress\Redis\AutoRefreshClient;
use Predis;

class Redis
{

    /**
     * @var static
     */
    protected static $instance;

    /**
     * @var Predis\ClientInterface
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
     * Gets singleton instance of this wrapper.
     *
     * @return static
     */
    public static function getInstance()
    {
        if (!(static::$instance instanceof static)) {
            static::$instance = new static(null, null, INSTANCE_ID);
        }
        return static::$instance;
    }

    /**
     * Gets client within singleton instance.
     *
     * @return Predis\ClientInterface
     */
    public static function getClientInstance()
    {
        return static::getInstance()->getClient();
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
        $this->setClient(new AutoRefreshClient($parameters, $options));
        $this->instanceId = $instanceId;
        return $this;
    }

    /**
     * @return Predis\ClientInterface
     */
    public function getClient()
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
     * The result is stored in "$base_key:sha256(serialize($params))".
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

        $redis = $this->getClient();
        $key   = $base_key . ':' . $this->instanceId . ':' . hash('sha256', serialize($params));

        // Would use Redis hashes, but individual keys don't have expiry.
        $is_result_called = false;
        $result           = null;
        try {
            $result = $redis->get($key);
            if (!isset($result)) {
                $is_result_called = true;
                $result           = call_user_func($callback, $params);
                if ($timeout > 0) {
                    $redis->setex($key, $timeout, serialize($result));
                } else {
                    $redis->set($key, serialize($result));
                }
            } else {
                // Results should now always be serialized before storage.
                // Older results might not be, though.
                $unserializedResult = unserialize($result);
                if ($unserializedResult !== false || $result === 'b:0;') {
                    // if serialized result is legitimately boolean false
                    $result = $unserializedResult;
                }
            }
        } catch (Predis\PredisException $e) {
            trigger_error(sprintf('Redis exception: "%s"', $e->getMessage()), E_USER_WARNING);
            if (!$is_result_called) {
                $result = call_user_func($callback, $params);
            }
        }
        return $result;
    }

}