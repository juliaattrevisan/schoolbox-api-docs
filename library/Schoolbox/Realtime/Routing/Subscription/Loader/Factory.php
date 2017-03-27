<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Routing\Subscription\Loader;

use InvalidArgumentException;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use Schoolbox\Notif\Queue\Bindings\Generator;
use Schoolbox\UserMapper;

class Factory
{
    /**
     * @var ClientInterface
     */
    private $redis;
    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @param ClientInterface $redis
     * @param LoggerInterface $log
     */
    public function __construct(ClientInterface $redis, LoggerInterface $log)
    {
        $this->redis = $redis;
        $this->log   = $log;
    }

    /**
     * @param string $cacheKey
     * @return ILoader
     */
    public function get($cacheKey)
    {
        try {
            if (!$this->redis->exists($cacheKey)) {
                throw new InvalidArgumentException("No redis cache, falling back to generator");
            }
            // pull in from cache
            return new CacheLoader($this->redis, $cacheKey, $this->log);

        } catch (InvalidArgumentException $e) {
            // empty/invalid cache: load up the subscriptions manually
            $this->log->warning($e->getMessage());

            // TODO reconsider these hardcoded dependencies
            return new BindingLoader(new Generator(), UserMapper::getInstance(), $this->log);
        }

    }
}