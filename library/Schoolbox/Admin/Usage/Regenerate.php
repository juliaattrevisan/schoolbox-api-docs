<?php

namespace Schoolbox\Admin\Usage;

use Alaress\Database\Connector\IConnector;
use Alaress\Redis;
use Psr\Log\LoggerInterface;

class Regenerate
{
    const CACHE_TIMEOUT = 86400; //24hrs in seconds
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

    public function regenerateUsage(LoggerInterface $logger = null): void
    {
        $logger?->info("Clearing all cache's");
        //clear the cache for all cached queries
        foreach (self::CACHED_QUERIES as $cachedQuery) {
            $logger?->debug("Removing cache: {$cachedQuery}");
            $this->cache->dropKeyCache($cachedQuery, $this->database);
        }

        //regenerate all queries
        //SystemPayload
        $logger?->info("Generating SystemPayload");
        $callback = function ($database) {
            return (new Graph\GlobalUsers\SystemPayload($database))->generate();
        };
        $payload = $this->cache->cacheFunctionCall(self::CACHE_USAGE_SYSTEM, $callback, $this->database, self::CACHE_TIMEOUT);

        //RolePayload
        $logger?->info("Generating RolePayload");
        $callback = function ($database) {
            return (new Graph\GlobalUsers\RolePayload($database))->generate();
        };
        $payload = $this->cache->cacheFunctionCall(self::CACHE_USAGE_ROLE, $callback, $this->database, self::CACHE_TIMEOUT);

        //UserPayload
        $logger?->info("Generating UserPayload");
        $callback = function ($database) {
            return (new Graph\GlobalUsers\UserPayload($database))->generate();
        };
        $payload = $this->cache->cacheFunctionCall(self::CACHE_USAGE_USER, $callback, $this->database, self::CACHE_TIMEOUT);

        //FileTypePayload
        $logger?->info("Generating FileTypePayload");
        $callback = function ($database) {
            return (new Graph\GlobalUsers\FileTypePayload($database))->generate();
        };
        $payload = $this->cache->cacheFunctionCall(self::CACHE_USAGE_FILETYPE, $callback, $this->database, self::CACHE_TIMEOUT);
        $logger?->info("All Complete");
    }
}
