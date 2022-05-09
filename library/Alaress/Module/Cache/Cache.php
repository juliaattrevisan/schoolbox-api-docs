<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Module\Cache;

use Alaress\Web\Router\RouteCollection;
use JsonException;
use Predis\ClientInterface;

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
     * @throws JsonException if for some reason the module registrations can't
     *                       be decoded
     */
    public function fetchModuleRegistrations(): array
    {
        $modulesEncoded = $this->redis->get(self::MODULE_REGISTRATION_CACHE_KEY_PREFIX . INSTANCE_ID);
        return json_decode($modulesEncoded, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Fetch route table from cache.
     *
     * @return RouteCollection
     */
    public function fetchRouteTable(): RouteCollection
    {
        $routesSerialized = $this->redis->get(self::ROUTE_TABLE_CACHE_KEY_PREFIX . INSTANCE_ID);

        // Is unserializing this lump any better than loading all modules?
        return unserialize($routesSerialized);
    }
}
