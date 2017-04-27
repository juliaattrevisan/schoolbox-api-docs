<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Routing\Subscription\Loader;

use Alaress\Routing\IRouter;
use InvalidArgumentException;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;


/**
 * Class CacheLoader
 * @package Schoolbox\Realtime\Routing\Subscription\Loader
 */
class CacheLoader implements ILoader
{
    /** @var LoggerInterface */
    protected $log;

    /**
     * Represents the compressed cache data
     *
     * @var string
     */
    private $data;

    /**
     * @param ClientInterface $redis
     * @param string          $storageKey
     * @param LoggerInterface $log
     * @throws InvalidArgumentException
     */
    public function __construct(ClientInterface $redis, $storageKey, LoggerInterface $log)
    {
        // against my better judgement, this is in the constructor so we can verify the redis data
        $routesCompressed = $redis->get($storageKey);

        if (!(is_string($routesCompressed) && strlen($routesCompressed))) {
            throw new InvalidArgumentException('Invalid data returned from Redis, cannot restore from cache');
        }

        $this->data = $routesCompressed;
        $this->log = $log;
    }

    /**
     * @deprecated
     *
     * @param IRouter $router
     * @throws InvalidArgumentException
     * @return $this
     */
    public function loadSubscriptions(IRouter $router)
    {
        $this->log->debug('Inflating route tree');
        $routeTree = json_decode(gzuncompress($this->data), true);

        $this->log->debug('Reading cached routes');

        $count = 0;

        foreach ($routeTree as $path => $pathData) {
            foreach ($pathData as $key => $value) {
                $router->add($path, $key, $value);
                ++$count;
            }
        }

        $this->log->debug("$count routes read from cache");

        return $this;
    }


    /**
     * @param int $userId
     * @return string[]
     */
    public function getUserSubscriptions($userId)
    {
        $routes = [];

        $routeTree = json_decode(gzuncompress($this->data), true);

        foreach ($routeTree as $path => $pathData) {
            foreach ($pathData as $key => $value) {
                if ($key == $userId) {
                    $routes[] = $path;
                }
            }
        }

        return $routes;
    }

}