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
use Redis;
use Schoolbox\Realtime\Routing\Subscription\Loader\ILoader;

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
     * @param Redis|ClientInterface $redis
     * @param string                $storageKey
     * @param LoggerInterface       $log
     * @throws InvalidArgumentException
     */
    public function __construct(ClientInterface $redis, $storageKey, LoggerInterface $log)
    {
        // against my better judgement, this is in the constructor so we can verify the redis data
        $routesCompressed = $redis->get($storageKey);

        if (!(is_string($routesCompressed) && strlen($routesCompressed))) {
            throw new InvalidArgumentException("Invalid data returned from Redis, cannot restore from cache");
        }

        $this->data = $routesCompressed;
        $this->log = $log;
    }

    /**
     * @param IRouter $router
     * @throws InvalidArgumentException
     * @return $this
     */
    public function loadSubscriptions(IRouter $router)
    {
        $this->log->debug("Inflating route tree");
        $routeTree = json_decode(gzuncompress($this->data), true);

        $this->log->debug("Installing cached routes");

        $count = 0;

        foreach ($routeTree as $path => $pathData) {
            foreach ($pathData as $key => $value) {
                $router->add($path, $key, $value);
                ++$count;
            }
        }

        $this->log->debug("$count routes installed from cache");

        return $this;
    }
}