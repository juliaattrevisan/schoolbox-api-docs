<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace External;

use Alaress\Redis;
use function call_user_func_array;
use function get_class;
use InvalidArgumentException;
use Schoolbox\External;
use function trigger_error;

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
    private $offline;

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
        $this->database = $database;
        $this->offline  = $offline;
        $this->cache    = $cache;

        if ($timeout === null) {
            // Null is fine
            $this->timeout = $timeout;
        } elseif ($timeout === (string)(int)$timeout) {
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
     */
    protected function bypassCache(string $method, array $params)
    {
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
     */
    protected function getCachedResult(string $method, array $params, int $timeoutOverride = null)
    {
        if ($this->isOffline()) {
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
        catch (External\Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);

            // If there was ANY problem with the connection, fall back to offline implementation for remainder of request
            $this->database = $this->offline;

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
     * Returns whether this connector is in offline mode
     *
     * @return bool
     */
    private function isOffline(): bool
    {
        return $this->database instanceof IOfflineConnector;
    }
}
