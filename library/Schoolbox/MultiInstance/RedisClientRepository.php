<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\MultiInstance;

use Alaress\Redis\Async\Client as AsyncClient;
use Alaress\Redis\AutoRefreshClient;
use Predis\ClientInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Config\ConfigMapper;

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
     * Cached async clients, grouped by DSN rather than instance ID.
     *
     * Some instances may share a Redis URL, there's no point creating another
     * identical connection.
     *
     * @var array<string, AsyncClient>
     */
    private array $asyncClients = [];

    /**
     * Constructor
     *
     * @param ConfigRepository $configRepository Config repository: required for fetching Redis URLs for instances.
     * @param LoopInterface    $loop             Event loop: required for building async clients.
     */
    public function __construct(private ConfigRepository $configRepository, private LoopInterface $loop)
    {
    }

    /**
     * Build the storage client for this instance.
     *
     * This client persists data to disk, thus is suitable for persistence of
     * data: however, it is slower than the cache-only client.
     *
     * @param string $instanceId
     * @return ClientInterface
     */
    public function fetchStorageClientForInstance(string $instanceId): ClientInterface
    {
        $dsn = $this->configRepository
            ->fetchConfigForInstance($instanceId)
            ->get(ConfigMapper::NAME_REDIS_URL);
        return $this->clients[$dsn] ??= $this->buildClientFromDsn($dsn);
    }

    /**
     * Build the cache client for this instance.
     *
     * This client is ephemeral (memory-only, does not persist data to disk):
     * thus this is more performant than the storage client, but is unsuitable
     * for data which must be persisted.
     *
     * @param string $instanceId
     * @return ClientInterface
     */
    public function fetchCacheClientForInstance(string $instanceId): ClientInterface
    {
        $dsn = $this->configRepository
            ->fetchConfigForInstance($instanceId)
            ->get(ConfigMapper::NAME_REDIS_EPHEMERAL_URL);
        return $this->clients[$dsn] ??= $this->buildClientFromDsn($dsn);
    }

    /**
     * Build the asynchronous storage client for this instance.
     *
     * This client is required for subscribing to queues.
     *
     * @param string $instanceId
     * @return AsyncClient
     */
    public function fetchAsyncStorageClientForInstance(string $instanceId): AsyncClient
    {
        $dsn = $this->configRepository
            ->fetchConfigForInstance($instanceId)
            ->get(ConfigMapper::NAME_REDIS_URL);
        return $this->asyncClients[$dsn] ??= new AsyncClient($dsn, $this->loop);
    }

    /**
     * Build a synchronous client given a DSN.
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
