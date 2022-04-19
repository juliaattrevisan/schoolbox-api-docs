<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing\Subscription\Loader;

use InvalidArgumentException;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Schoolbox\Notif\Queue\Bindings\Generator;
use Schoolbox\Notif\Queue\Bindings\GeneratorInterface;
use Schoolbox\Resource\Folder;
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

    /** @var Folder\Mapper */
    private $folderMapper;


    /**
     * @param ClientInterface $redis
     */
    public function __construct(ClientInterface $redis)
    {
        $this->redis = $redis;
        $this->log   = new NullLogger();
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
        return new BindingLoader($this->getGenerator(), $this->getUserMapper(), $this->getFolderMapper(), $this->log);
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
     * @param Folder\Mapper $folderMapper
     * @return $this
     */
    public function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
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


    /**
     * @return Folder\Mapper
     */
    private function getFolderMapper()
    {
        if (null === $this->folderMapper) {
            $this->setFolderMapper(Folder\Mapper::getInstance());
        }
        return $this->folderMapper;
    }
}
