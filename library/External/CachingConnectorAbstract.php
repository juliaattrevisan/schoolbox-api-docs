<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External;

use Alaress\Redis;
use InvalidArgumentException;
use Schoolbox\External;

use function call_user_func_array;
use function get_class;

/**
 * Common implementation for classes that:
 * * cache their connector method results, and
 * * fallback to offline mode if errors encountered
 *
 * Extending classes can decide on a per-method basis if the method should cache or not
 */
class CachingConnectorAbstract
{

    /**
     * May or may not be online/offline
     *
     * @var IConnector
     */
    private $database;

    /**
     * Always offline
     *
     * @var IOfflineConnector
     */
    private $offlineConnector;

    /** @var bool */
    private $isOffline = false;

    /** @var Redis */
    private $cache;

    /** @var int|null */
    private $timeout;


    /**
     * @param IConnector        $database
     * @param IOfflineConnector $offline
     * @param Redis             $cache
     * @param string|null       $timeout (Expects string to support value directly fetched from DB)
     * @throws InvalidArgumentException
     */
    public function __construct(IConnector $database, IOfflineConnector $offline, Redis $cache, string $timeout = null)
    {
        $this->database         = $database;
        $this->offlineConnector = $offline;
        $this->cache            = $cache;

        if ($timeout === null) {
            // Null is fine
            $this->timeout = $timeout;
        } elseif (is_numeric($timeout)) {
            // Ensure the string is an int
            $this->timeout = (int)$timeout;
        } else {
            // Reject anything else
            throw new InvalidArgumentException("Timeout must be NULL or an integer string");
        }
    }


    /**
     * Execute the method and return results without passing through cache layer
     *
     * @param string $method
     * @param array  $params
     * @return mixed
     * @throws External\Exception
     */
    protected function bypassCache(string $method, array $params)
    {
        if (PHP_SAPI === 'cli' && $this->isOffline) {
            throw new External\Exception('SIS connector is offline.');
        }
        return call_user_func_array([$this->database, $method], $params);
    }


    /**
     * Executes a connector method with the specified params and returns the results.
     *
     * Optionally caches the result, depending on $this->timeout
     *
     * @param string   $method
     * @param array    $params
     * @param int|null $timeoutOverride Optionally override configured timeout
     * @return mixed
     * @throws External\Exception
     */
    protected function getCachedResult(string $method, array $params, int $timeoutOverride = null)
    {
        if ($this->isOffline) {
            return $this->bypassCache($method, $params);
        }

        $key      = get_class($this->database) . '.' . $method;
        $database = $this->database;
        $callback = static function ($params) use ($database, $method) {
            return call_user_func_array([$database, $method], $params);
        };

        try {
            return $this->cache->cacheFunctionCall($key, $callback, $params, $timeoutOverride ?? $this->timeout);
        } /** @noinspection PhpRedundantCatchClauseInspection */
        catch (\Alaress\Database\Exception $e) {
            $this->isOffline = true;
            // If there was ANY problem with the connection, fall back to offline implementation for remainder of request
            if (PHP_SAPI === 'cli') {
                throw new \Schoolbox\External\Exception('SIS connector failed to execute function: ' . $e->getMessage());
            } else {
                $this->database = $this->offlineConnector;
            }
            return $this->bypassCache($method, $params);
        }
    }


    /**
     * @return int|null
     */
    protected function getTimeout(): ?int
    {
        return $this->timeout;
    }


    /**
     * @return IConnector
     */
    public function getDatabase(): IConnector
    {
        return $this->database;
    }
}
