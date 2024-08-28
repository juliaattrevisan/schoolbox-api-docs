<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing\Cache\Redis;

use Alaress\Async\Queue\RedisQueue;
use Alaress\Async\Synchroniser;
use Alaress\Routing\FlatRouter;
use Alaress_Byte_Format;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use Schoolbox\Realtime\Routing\Cache\WritableCache;
use Schoolbox\Realtime\Routing\Subscription;
use UnderflowException;

/**
 * Writable cache of routes, backed by a Redis instance.
 */
class WriteCache extends ReadCache implements WritableCache
{
    const SIGNAL_PATTERN = 'scheduled.%s.reload';

    /**
     * Constructor
     *
     * @param ClientInterface             $redis
     * @param Subscription\Loader\Factory $loaderFactory
     * @param ClientInterface             $redisQueue
     */
    public function __construct(
        ClientInterface $redis,
        private Subscription\Loader\Factory $loaderFactory,
        private ClientInterface $redisQueue
    ) {
        parent::__construct($redis);
        $this->log = new \Psr\Log\NullLogger();
    }


    /**
     * @param string $instanceId
     * @param int[]  $userIds
     * @return void
     */
    public function cacheUsers($instanceId, array $userIds)
    {
        $routeStorageKey = $this->getCacheKey($instanceId);

        // ensure this block of code is only ever executed by exactly one process at a time
        $this->buildSynchroniser()->synchronise(
            $userIds,
            $this->buildSynchroniserCallback($routeStorageKey),
            $this->buildSynchroniserDoneCallback($instanceId)
        );
    }


    /**
     * @param string $instanceId
     * @return void
     */
    public function flushCacheUsersQueue($instanceId)
    {
        $this->buildSynchroniser()->flush(
            $this->buildSynchroniserCallback($this->getCacheKey($instanceId)),
            $this->buildSynchroniserDoneCallback($instanceId)
        );
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
     * @param string $instanceId
     * @return void
     */
    public function cacheRedirects($instanceId)
    {
        $this->log->info('Calculating redirect bindings');

        // retrieve all routes from the cache
        $cachedRoutes = $this->fetchCache($this->getCacheKey($instanceId));

        // remove all redirects from the cache
        foreach ($cachedRoutes as $path => $pathData) {
            foreach ($pathData as $key => $value) {
                if (!is_int($key)) {
                    // if it's not an integer, remove it
                    unset($cachedRoutes[$path][$key]);
                }
            }
        }

        // remove any now-empty keys
        $cachedRoutes = array_filter($cachedRoutes);

        foreach ($this->loaderFactory->getNoCache()->getRedirects() as $from => $to) {
            // put these redirects into the cache
            $cachedRoutes[$from][$to] = true;
        }

        // load up a router with the new dataset
        $router = new FlatRouter();

        // reinsert all remaining routes
        foreach ($cachedRoutes as $path => $pathData) {
            foreach ($pathData as $key => $value) {
                $router->add($path, $key, $value);
            }
        }

        $this->writeCache($router, $this->getCacheKey($instanceId));

        $this->broadcastReload($instanceId);
    }

    /**
     * @inheritDoc
     */
    public function setLog(LoggerInterface $log): ReadCache
    {
        $this->loaderFactory->setLog($log);
        return parent::setLog($log);
    }

    /**
     * Build a synchroniser object for cacheUsers().
     *
     * @return Synchroniser
     */
    private function buildSynchroniser(): Synchroniser
    {
        return new Synchroniser($this, $this->redis);
    }

    /**
     * Build a callback for the synchroniser object for cacheUsers().
     *
     * @param string $routeStorageKey
     * @return callable
     */
    private function buildSynchroniserCallback(string $routeStorageKey): callable
    {
        return function ($userId) use ($routeStorageKey): void {
            // this closure will be called multiple times consecutively if there are concurrent requests
            try {
                $router = $this->bindIndividual($userId, $routeStorageKey);
                $this->writeCache($router, $routeStorageKey);
            } catch (UnderflowException $e) {
                // this is fine; continue with rest
                $this->log->notice($e->getMessage());
            }
        };
    }

    /**
     * Build an on-complete callback for the synchroniser object for cacheUsers().
     *
     * @param string $instanceId
     * @return callable
     */
    private function buildSynchroniserDoneCallback(string $instanceId): callable
    {
        return function () use ($instanceId): void {
            $this->broadcastReload($instanceId);
        };
    }


    /**
     * @param string $instanceId
     */
    private function broadcastReload(string $instanceId): void
    {
        $reloadChannel = sprintf(self::SIGNAL_PATTERN, $instanceId);

        $this->log->info("Broadcasting `$reloadChannel`", [ 'char' => '>' ]);

        $this->redisQueue->publish($reloadChannel, '');
    }


    /**
     * @param FlatRouter $router
     * @param string     $routeStorageKey
     */
    private function writeCache(FlatRouter $router, string $routeStorageKey): void
    {
        $json = json_encode($router->getAll());
        $gz = gzcompress($json, RedisQueue::COMPRESSION_LEVEL);

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
    private function bindIndividual(int $userId, string $routeStorageKey): FlatRouter
    {
        $this->log->info("Calculating bindings for user $userId");
        $allRoutes = $this->fetchCache($routeStorageKey);

        $router = new FlatRouter();

        // compile user routes
        $userRoutes  = $this->generateRoutesForUser($userId);
        $changeCount = 0;

        // remove any bindings no longer applicable to this user
        foreach ($allRoutes as $path => $pathData) {
            if (isset($pathData[$userId]) && empty($userRoutes[$path])) {
                $this->log->info("User $userId - `$path`", [ 'char' => '-' ]);
                unset($allRoutes[$path][$userId]);
                ++$changeCount;
            }
        }

        // reinsert all remaining routes
        foreach ($allRoutes as $path => $pathData) {
            foreach ($pathData as $key => $value) {
                $router->add($path, $key, $value);
            }
        }

        // compute the positive diff (ie. new subscriptions)
        $userDiff = $userRoutes;

        foreach ($allRoutes as $path => $pathData) {
            if (array_key_exists($userId, $pathData)) {
                unset($userDiff[$path]);
            }
        }

        // overlay this user's bindings to account for any new ones
        foreach ($userDiff as $path => $pathData) {
            $router->add($path, $userId, true);
            $this->log->info("User $userId + `$path`", [ 'char' => '+' ]);
            ++$changeCount;
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
    private function bindEveryone(Subscription\Loader\ILoader $loader, FlatRouter $router): FlatRouter
    {
        // this will calculate and load all subscriptions into the router
        $loader->loadSubscriptions($router);

        return $router;
    }


    /**
     * Essentially just converts generator bindings into a route list
     *
     * @param int $userId
     * @return array
     */
    private function generateRoutesForUser(int $userId): array
    {
        return $this->loaderFactory->getNoCache()->getUserSubscriptions($userId);
    }


    /**
     * @param string $routeStorageKey
     * @return string[][]
     */
    private function fetchCache(string $routeStorageKey): array
    {
        $router = new FlatRouter();

        // start by loading from cache
        $this->loaderFactory->get($routeStorageKey)->loadSubscriptions($router);

        // extract the list and reset the router
        return $router->getAll();
    }
}
