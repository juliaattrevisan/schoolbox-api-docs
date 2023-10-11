<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\MultiInstance;

use Alaress\Redis\AutoRefreshClient;
use Predis\ClientInterface;

/**
 * Repository for Redis clients for all instances this node knows about.
 */
class RedisClientRepository
{
    /**
     * Cached clients, grouped by DSN rather than instance ID.
     *
     * Some instances may share a Redis URL, there's no point creating another
     * identical connection.
     *
     * @var array<string, ClientInterface>
     */
    private array $clients = [];

    /**
     * Constructor
     *
     * @param ConfigRepository $configRepository Config repository: required for fetching Redis URLs for instances.
     * @param string           $configKey        The config key which stores the Redis URL.
     *                                           Should be one of the `Schoolbox\Config\ConfigMapper` constants
     *                                           `NAME_REDIS_URL` or `NAME_REDIS_EPHEMERAL_URL`.
     */
    public function __construct(private ConfigRepository $configRepository, private string $configKey)
    {
    }

    /**
     * Build a client for this instance.
     *
     * @param string $instanceId
     * @return ClientInterface
     */
    public function fetchForInstance(string $instanceId): ClientInterface
    {
        $dsn = $this->configRepository
            ->fetchConfigForInstance($instanceId)
            ->get($this->configKey);
        return $this->clients[$dsn] ??= $this->buildClientFromDsn($dsn);
    }

    /**
     * Build a client given a DSN.
     *
     * @param string $dsn
     * @return ClientInterface
     */
    private function buildClientFromDsn(string $dsn): ClientInterface
    {
        $parameters = parse_url($dsn);
        $parameters['persistent'] = true;
        return new AutoRefreshClient($parameters);
    }
}
