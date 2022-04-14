<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing\Cache\Redis;

use Alaress\Routing\IRouter;
use InvalidArgumentException;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use Schoolbox\Realtime\Routing\Cache\ReadableCache;

/**
 * Read-only cache of routes, backed by a Redis instance.
 */
class ReadCache implements ReadableCache
{
    const CACHE_PATTERN  = 'm:%s:routes';

    /** @var ClientInterface */
    protected $redis;

    /** @var LoggerInterface */
    protected $log;


    /**
     * @param ClientInterface $redis
     */
    public function __construct(ClientInterface $redis)
    {
        $this->redis = $redis;
        $this->log = new \Psr\Log\NullLogger();
    }

    /**
     * @param LoggerInterface $log
     * @return $this
     */
    public function setLog(LoggerInterface $log): self
    {
        $this->log = $log;
        return $this;
    }

    /**
     * @param string  $instanceId
     * @param IRouter $router
     * @return void
     */
    public function readIntoRouter($instanceId, IRouter $router)
    {
        try {
            $this->readFromCache($instanceId, $router);
        } catch (InvalidArgumentException $e) {
            $this->log->notice("No cache existed for instance '$instanceId'");
        }
    }


    /**
     * @param int     $instanceId
     * @param IRouter $router
     * @throws InvalidArgumentException
     * @return void
     */
    protected function readFromCache($instanceId, IRouter $router)
    {
        $routesCompressed = (string)$this->redis->get($this->getCacheKey($instanceId));

        if ($routesCompressed === '') {
            throw new InvalidArgumentException('Invalid data returned from Redis, cannot restore from cache');
        }

        $this->log->debug('Inflating route tree');

        /** @var string[][] $routeTree */
        $routeTree = json_decode(gzuncompress($routesCompressed), true);

        $this->log->debug('Installing cached routes');

        // drop existing routes from router
        $router->dropAll();
        $count = 0;

        foreach ($routeTree as $path => $pathData) {
            foreach ($pathData as $key => $value) {
                $router->add($path, $key, $value);
                ++$count;
            }
        }

        $this->log->debug("$count routes installed from cache");
    }


    /**
     * @param string $instanceId
     * @return string
     */
    protected function getCacheKey($instanceId)
    {
        return sprintf(self::CACHE_PATTERN, $instanceId);
    }
}
