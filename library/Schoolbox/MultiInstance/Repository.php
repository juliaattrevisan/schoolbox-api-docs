<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\MultiInstance;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Factory as DatabaseFactory;
use OutOfBoundsException;
use Schoolbox\Config\ArrayLoader;
use Schoolbox\Config\DataSource;
use Schoolbox\IConfigLoader;

/**
 * Repository of connectors for all instances this node knows about.
 *
 * May retrieve the following fully-realised objects for an instance:
 * * Database connector
 * * Config loader
 *
 * Cannot yet retrieve:
 * * DI container
 * * Mappers
 *
 * @template TInstanceConfig as array{
 *     db_host: string,
 *     db_schema: string,
 *     db_user: string,
 *     db_pass: string,
 *     db_host_read?: string,
 *     db_schema_read?: string,
 *     db_user_read?: string,
 *     db_pass_read?: string,
 * }
 */
class Repository
{
    /**
     * A cache of database connectors per instance.
     *
     * @var array<string, IConnector>
     */
    private array $instanceConnectors = [];

    /**
     * A cache of configs per instance.
     *
     * @var array<string, IConfigLoader>
     */
    private array $instanceConfigs = [];

    /**
     * Constructor
     *
     * @param array<string, TInstanceConfig> $instances A list of instances, as parsed from an .ini file.
     * @param DatabaseFactory                $factory   A factory for building database connections.
     */
    public function __construct(
        private array $instances,
        private DatabaseFactory $factory
    ) {
    }

    /**
     * Retrieve a database connection for a given instance.
     *
     * @param string $instanceId
     * @return IConnector
     * @throws OutOfBoundsException if `$instanceId` is unknown
     */
    public function getDatabaseForInstance(string $instanceId): IConnector
    {
        $instanceData = $this->getInstanceData($instanceId);

        // Build a connection if required: assuming read-only for now
        if (!isset($this->instanceConnectors[$instanceId])) {
            $this->instanceConnectors[$instanceId] = $this->factory->createMysql();
            $this->instanceConnectors[$instanceId]->connect(
                $instanceData['db_host'],
                $instanceData['db_schema'],
                $instanceData['db_user'],
                $instanceData['db_pass'],
            );
        }
        return $this->instanceConnectors[$instanceId];
    }

    /**
     * Retrieve a config for a given instance.
     *
     * @param string $instanceId
     * @return IConfigLoader
     * @throws OutOfBoundsException if `$instanceId` is unknown
     */
    public function getConfigForInstance(string $instanceId): IConfigLoader
    {
        if (!isset($this->instanceConfigs[$instanceId])) {
            $this->instanceConfigs[$instanceId] = new ArrayLoader($this->loadConfig($instanceId));
        }
        return $this->instanceConfigs[$instanceId];
    }

    /**
     * Return the instance data for a given instance.
     *
     * @param string $instanceId
     * @return TInstanceConfig
     * @throws OutOfBoundsException if `$instanceId` is unknown
     */
    private function getInstanceData(string $instanceId): array
    {
        if (!isset($this->instances[$instanceId])) {
            throw new OutOfBoundsException(sprintf('Unknown instance ID "%s"', $instanceId));
        }
        return $this->instances[$instanceId];
    }

    /**
     * Loads configuration for the given instance.
     *
     * @param string $instanceId
     * @return array The instance's configuration.
     * @throws OutOfBoundsException if `$instanceId` is unknown
     */
    private function loadConfig(string $instanceId): array
    {
        return (new DataSource())->findAllConfigs($this->getDatabaseForInstance($instanceId));
    }
}
