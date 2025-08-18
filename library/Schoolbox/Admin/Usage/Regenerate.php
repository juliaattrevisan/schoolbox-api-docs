<?php

namespace Schoolbox\Admin\Usage;

use Alaress\Database\Connector\IConnector;
use Alaress\Redis;
use Psr\Log\LoggerInterface;

class Regenerate
{
    /**
     * Cache the usage calculations for 24hrs
    */
    const CACHE_TIMEOUT = 86400;
    const CACHE_USAGE_SYSTEM = 'system_usage_system';
    const CACHE_USAGE_ROLE = 'system_usage_role';
    const CACHE_USAGE_USER = 'system_usage_user';
    const CACHE_USAGE_FILETYPE = 'system_usage_fileType';
    const CACHED_QUERIES = [
        self::CACHE_USAGE_SYSTEM,
        self::CACHE_USAGE_ROLE,
        self::CACHE_USAGE_USER,
        self::CACHE_USAGE_FILETYPE,

    ];
    /**
     *
     */
    public function __construct(
        private Redis $cache,
        private IConnector $database
    ) {
    }

    /**
     * Deletes all caches for the storage system calculation screen from within redis,
     * Then will re calculate the storage and store that result in redis
     *
     * @param LoggerInterface|null $logger //used for the cli script tools/storage/usageCache.php
     * @return void
     */
    public function regenerateUsage(?LoggerInterface $logger = null): void
    {
        $logger?->info("Clearing all cache's");
        //clear the cache for all cached queries
        foreach (self::CACHED_QUERIES as $cachedQuery) {
            $logger?->debug("Removing cache: {$cachedQuery}");
            $count = $this->cache->dropKeyCache($cachedQuery, '');
            $logger?->debug("Removed: #{$count} instances of: {$cachedQuery}");
        }

        //regenerate all queries
        //SystemPayload
        $logger?->info("Generating SystemPayload");
        $callback = function ($database) {
            return [
                'time' => time(),
                'data' => (new Graph\GlobalUsers\SystemPayload($this->database))->generate()
                ];
        };
        $payload = $this->cache->cacheFunctionCall(self::CACHE_USAGE_SYSTEM, $callback, '', self::CACHE_TIMEOUT);

        //RolePayload
        $logger?->info("Generating RolePayload");
        $callback = function ($database) {
            return [
                'time' => time(),
                'data' => (new Graph\GlobalUsers\RolePayload($this->database))->generate()
                ];
        };
        $payload = $this->cache->cacheFunctionCall(self::CACHE_USAGE_ROLE, $callback, '', self::CACHE_TIMEOUT);

        //UserPayload
        $logger?->info("Generating UserPayload");
        $callback = function ($database) {
            return [
                'time' => time(),
                'data' => (new Graph\GlobalUsers\UserPayload($this->database))->generate()
                ];
        };
        $payload = $this->cache->cacheFunctionCall(self::CACHE_USAGE_USER, $callback, '', self::CACHE_TIMEOUT);

        //FileTypePayload
        $logger?->info("Generating FileTypePayload");
        $callback = function ($database) {
            return [
                'time' => time(),
                'data' => (new Graph\GlobalUsers\FileTypePayload($this->database))->generate()
                ];
        };
        $payload = $this->cache->cacheFunctionCall(self::CACHE_USAGE_FILETYPE, $callback, '', self::CACHE_TIMEOUT);
        $logger?->info("Calculation of storage complete");
    }
}
