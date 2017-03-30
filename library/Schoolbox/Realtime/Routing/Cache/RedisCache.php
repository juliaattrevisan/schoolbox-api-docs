<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Routing\Cache;

use Alaress\Routing\FlatRouter;
use Alaress\Routing\IRouter;
use Alaress_Byte_Format;
use InvalidArgumentException;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use Schoolbox\Notif\Queue\Bindings\GeneratorInterface;
use Schoolbox\Realtime\Routing\Subscription;
use Schoolbox\UserMapper;
use UnderflowException;


class RedisCache implements ICache
{

    const CACHE_PATTERN  = 'm:%s:routes';
    const SIGNAL_PATTERN = 'routed.%s.reload';


    /** @var Subscription\Loader\Factory */
    private $loaderFactory;

    /** @var LoggerInterface */
    private $log;

    /** @var ClientInterface */
    private $redis;

    /** @var GeneratorInterface */
    private $generator;

    /** @var UserMapper */
    private $userMapper;


    /**
     * @param Subscription\Loader\Factory $loaderFactory
     * @param LoggerInterface             $log
     * @param ClientInterface             $redis
     * @param GeneratorInterface          $generator
     * @param UserMapper                  $userMapper
     */
    public function __construct(
        Subscription\Loader\Factory $loaderFactory,
        LoggerInterface $log,
        ClientInterface $redis,
        GeneratorInterface $generator,
        UserMapper $userMapper
    ) {
        $this->loaderFactory = $loaderFactory;
        $this->log           = $log;
        $this->redis         = $redis;
        $this->generator     = $generator;
        $this->userMapper    = $userMapper;
    }


    /**
     * @inheritdoc
     */
    public function cacheUser($instanceId, $userId)
    {
        $this->obtainSemaphoreLock();

        try {
            $routeStorageKey = $this->getCacheKey($instanceId);

            $router = $this->bindIndividual($userId, $routeStorageKey);
            $this->writeCache($router, $routeStorageKey);

            $this->releaseSemaphore();
            $this->broadcastReload($instanceId);

        } catch (UnderflowException $e) {
            $this->log->notice($e->getMessage());
            $this->releaseSemaphore();
        }

    }


    /**
     * @inheritdoc
     * @throws UnderflowException
     */
    public function cacheAllUsers($instanceId)
    {
        $router = $this->bindEveryone($this->loaderFactory->getNoCache(), new FlatRouter());
        $this->writeCache($router, $this->getCacheKey($instanceId));

        $this->broadcastReload($instanceId);
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
            $loader = $this->loaderFactory->getNoCache();
            $loader->loadSubscriptions($router);
        }
    }


    /**
     * @param int     $instanceId
     * @param IRouter $router
     * @throws InvalidArgumentException
     * @return void
     */
    private function readFromCache($instanceId, IRouter $router)
    {
        $routesCompressed = (string)$this->redis->get($this->getCacheKey($instanceId));

        if ($routesCompressed === '') {
            throw new InvalidArgumentException('Invalid data returned from Redis, cannot restore from cache');
        }

        $this->log->debug('Inflating route tree');

        /** @var string[][] $routeTree */
        $routeTree = json_decode(gzuncompress($routesCompressed), true);

        $this->log->debug('Installing cached routes');

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
     * @return void
     */
    private function obtainSemaphoreLock()
    {
        // TODO SEMAPHORE!!!
        $this->log->error('[TODO] Cache lock obtained');
    }

    /**
     * @return void
     */
    private function releaseSemaphore()
    {
        // TODO release semaphore
        $this->log->error('[TODO] Cache lock released');
    }


    /**
     * @param string $instanceId
     * @return string
     */
    private function getCacheKey($instanceId)
    {
        return sprintf(self::CACHE_PATTERN, $instanceId);
    }


    /**
     * @param string $instanceId
     */
    private function broadcastReload($instanceId)
    {
        $reloadChannel = sprintf(self::SIGNAL_PATTERN, $instanceId);

        $this->log->info("Broadcasting `$reloadChannel`", [ 'char' => '>' ]);

        $this->redis->publish($reloadChannel, '');
    }


    /**
     * @param FlatRouter $router
     * @param string     $routeStorageKey
     */
    private function writeCache(FlatRouter $router, $routeStorageKey)
    {
        $json = json_encode($router->getAll());
        $gz = gzcompress($json, 9);

        $this->log->info(sprintf('Compressing route tree: %s => %s', Alaress_Byte_Format::bytesToString(strlen($json)), Alaress_Byte_Format::bytesToString(strlen($gz))));

        $this->redis->set($routeStorageKey, $gz);

        $this->log->info("Route tree stored in `$routeStorageKey`", [ 'char' => '+' ]);
    }


    /**
     * @param int    $userId
     * @param string $routeStorageKey
     * @throws UnderflowException
     * @return FlatRouter
     */
    private function bindIndividual($userId, $routeStorageKey)
    {
        $this->log->info("Calculating bindings for user $userId");
        $router = new FlatRouter();

        // start by loading from cache
        $this->loaderFactory->get($routeStorageKey)->loadSubscriptions($router);

        // use the generator to compute new bindings for the user
        $userBindings = $this->generator->generateBindingsForUser($this->userMapper->findById($userId));

        // extract the list and reset the router
        $allRoutes = $router->getAll();
        $router = new FlatRouter();

        // compile user routes
        $userRoutes  = [];
        $changeCount = 0;

        foreach ($userBindings as $prefix => $data) {
            foreach ($data as $target) {
                $binding = "*.notify.$prefix.$target";
                $userRoutes[$binding] = true;
            }
        }

        // remove any bindings no longer applicable to this user
        foreach ($allRoutes as $path => $pathData) {
            if (isset($pathData[$userId]) && empty($userRoutes[$path])) {
                $this->log->info("User $userId - `$path`", [ 'char' => '-' ]);
                unset ($allRoutes[$path][$userId]);
                ++$changeCount;
            }
        }

        // reinsert all remaining routes
        foreach ($allRoutes as $path => $pathData) {
            foreach ($pathData as $key => $value) {
                $router->add($path, $key, $value);
            }
        }

        // overlay this user's bindings to account for any new ones
        foreach ($userRoutes as $path => $_) {
            $a = count($router->getAll());
            $router->add($path, $userId, true);
            $b = count($router->getAll());
            if ($b !== $a) {
                $this->log->info("User $userId + `$path`", [ 'char' => '+' ]);
                ++$changeCount;
            }
        }

        if ($changeCount === 0) {
            throw new UnderflowException("No changes made to bindings for user $userId");
        }

        return $router;
    }


    /**
     * @param Subscription\Loader\ILoader $loader
     * @param FlatRouter                  $router
     * @return FlatRouter
     */
    private function bindEveryone(Subscription\Loader\ILoader $loader, FlatRouter $router)
    {
        // this will calculate and load all subscriptions into the router
        $loader->loadSubscriptions($router);

        return $router;
    }

}