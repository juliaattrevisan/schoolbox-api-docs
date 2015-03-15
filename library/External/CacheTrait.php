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

    protected function getCache()
    {
        return $this->cache;
    }

    protected function setCache($cache)
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * Caches a call to an external connector.
     *
     * Should be called in the following manner:
     * <code>
     * return $this->getCachedResult(__FUNCTION__, func_get_args());
     * </code>
     *
     * @param string $method A method defined in the external connector
     * @param array  $params Parameters passed to the array
     *
     * @return mixed
     */
    protected function getCachedResult($method, $params)
    {
        return $this->getCache()->cacheFunctionCall(
            __CLASS__.'.'.$method,
            function ($params) use ($method) {
                return call_user_func_array(
                    array($this->getDatabase(), $method),
                    $params
                );
            },
            $params
        );
    }
}
