<?php
/**
 * A cache trait for the external database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Alaress\Redis;

trait External_CacheTrait
{
    /**
     * The Redis cache that the external connector uses.
     *
     * @var Redis
     */
    protected $cache;

    /**
     * The timeout for the external connector cache.
     *
     * @var int
     */
    protected $timeout;

    /**
     * @return Redis
     */
    protected function getCache()
    {
        return $this->cache;
    }

    /**
     * @param Redis $cache
     *
     * @return $this
     */
    protected function setCache($cache)
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * @return int
     */
    protected function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * @param  int $timeout
     *
     * @return $this
     */
    protected function setTimeout($timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Caches a call to an external connector.
     *
     * Should be called in the following manner:
     * <code>
     * return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
     * </code>
     *
     * @param string $method  A method defined in the external connector
     * @param array  $params  Parameters passed to the array
     * @param int    $timeout Timeout of cache result, or null if no timeout
     *
     * @return mixed
     */
    protected function getCachedResult($method, $params, $timeout = null)
    {
        $database = $this->getDatabase();
        return $this->getCache()->cacheFunctionCall(
            get_class($database).'.'.$method,
            function ($params) use ($database, $method) {
                return call_user_func_array(
                    array($database, $method),
                    $params
                );
            },
            $params,
            $timeout
        );
    }
}
