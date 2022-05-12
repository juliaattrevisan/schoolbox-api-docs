<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Module\Cache;

use Aura\Router\RouteCollection;
use JsonException;
use Predis\ClientInterface;
use TypeError;

class Cache
{
    /**
     * The prefix for the key within which the module registration is cached.
     *
     * Will be suffixed with the current instance ID.
     */
    public const MODULE_REGISTRATION_CACHE_KEY_PREFIX = 'moduleCache:';

    /**
     * The prefix for the key within which the route table is cached.
     *
     * Will be suffixed with the current instance ID.
     */
    public const ROUTE_TABLE_CACHE_KEY_PREFIX = 'routeCache:';

    /**
     * Redis client: the route table and module registrations are stored here.
     */
    private ClientInterface $redis;

    /**
     * Constructor
     *
     * @param ClientInterface $redis
     */
    public function __construct(ClientInterface $redis)
    {
        $this->redis = $redis;
    }

    /**
     * Fetch module registrations from cache.
     *
     * @return array
     * @throws FetchException if the module registration isn't in cache.
     * @throws TypeError      if somehow the module registration once decoded
     *                        is not an array?
     */
    public function fetchModuleRegistrations(): array
    {
        $modulesEncoded = $this->redis->get(self::MODULE_REGISTRATION_CACHE_KEY_PREFIX . INSTANCE_ID);
        if (!isset($modulesEncoded)) {
            throw new FetchException('Module registration not found in cache');
        }
        try {
            return json_decode($modulesEncoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new FetchException('Module registration found in cache, but is not valid JSON', null, $e);
        }
    }

    /**
     * Fetch route table from cache.
     *
     * @return RouteCollection
     * @throws FetchException if the route table isn't in cache.
     * @throws TypeError      if somehow the route table once decoded is not a
     *                        `RouteCollection`?
     */
    public function fetchRouteTable(): RouteCollection
    {
        $routesSerialized = $this->redis->get(self::ROUTE_TABLE_CACHE_KEY_PREFIX . INSTANCE_ID);
        if (!isset($routesSerialized)) {
            throw new FetchException('Route table not found in cache');
        }
        return unserialize($routesSerialized);
    }
}
