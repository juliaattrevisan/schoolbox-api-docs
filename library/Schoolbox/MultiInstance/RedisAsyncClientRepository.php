<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\MultiInstance;

use Alaress\Redis\Async\Client;
use React\EventLoop\LoopInterface;
use Schoolbox\Config\ConfigMapper;

/**
 * Repository for asynchronous Redis clients for all instances this node knows
 * about.
 *
 * This always creates connections to persistent Redis instances, because
 * there's no queue for the cached instance.
 */
class RedisAsyncClientRepository
{
    /**
     * Cached clients, grouped by DSN rather than instance ID.
     *
     * Some instances may share a Redis URL, there's no point creating another
     * identical connection.
     *
     * @var array<string, Client>
     */
    private array $clients = [];

    /**
     * Constructor
     *
     * @param ConfigRepository $configRepository Config repository: required for fetching Redis URLs for instances.
     * @param LoopInterface    $loop             Event loop: required for async clients.
     */
    public function __construct(private ConfigRepository $configRepository, private LoopInterface $loop)
    {
    }

    /**
     * Build an async client for this instance.
     *
     * @param string $instanceId
     * @return Client
     */
    public function fetchForInstance(string $instanceId): Client
    {
        $dsn = $this->configRepository
            ->fetchConfigForInstance($instanceId)
            ->get(ConfigMapper::NAME_REDIS_URL);
        return $this->clients[$dsn] ??= new Client($dsn, $this->loop);
    }
}
