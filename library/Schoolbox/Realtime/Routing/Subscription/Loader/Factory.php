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
use Schoolbox\Notif\Queue\Bindings\GeneratorInterface;
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

    /** @var GeneratorInterface */
    private $generator;

    /** @var UserMapper */
    private $userMapper;


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
                throw new InvalidArgumentException('No redis cache, falling back to generator');
            }
            // pull in from cache
            return new CacheLoader($this->redis, $cacheKey, $this->log);

        } catch (InvalidArgumentException $e) {
            // empty/invalid cache: load up the subscriptions manually
            $this->log->warning($e->getMessage());

            return $this->getNoCache();
        }
    }


    /**
     * @return ILoader
     */
    public function getNoCache()
    {
        return new BindingLoader($this->getGenerator(), $this->getUserMapper(), $this->log);
    }


    /**
     * @param GeneratorInterface $generator
     * @return static
     */
    public function setGenerator($generator)
    {
        $this->generator = $generator;
        return $this;
    }


    /**
     * @return GeneratorInterface
     */
    private function getGenerator()
    {
        if (null === $this->generator) {
            $this->setGenerator(new Generator());
        }

        return $this->generator;
    }


    /**
     * @param UserMapper $userMapper
     * @return static
     */
    public function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }


    /**
     * @return UserMapper
     */
    private function getUserMapper()
    {
        if (null === $this->userMapper) {
            $this->setUserMapper(UserMapper::getInstance());
        }
        return $this->userMapper;
    }

}