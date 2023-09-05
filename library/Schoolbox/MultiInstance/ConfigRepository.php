<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\MultiInstance;

use OutOfBoundsException;
use Schoolbox\Config\DataSource;
use Schoolbox\IConfigLoader;

/**
 * Repository of configs for each instance this node knows about.
 */
class ConfigRepository
{
    /**
     * Instance configs already created.
     *
     * @var array<string, IConfigLoader>
     */
    private array $instanceConfigs = [];

    /**
     * Constructor
     *
     * @param ConnectorRepository $connectorRepository Repository of database connections per-instance.
     * @param DataSource          $dataSource          Data source for configuration data: can fetch config data from a
     *                                                 database connection retrieved from `$connectorRepository`.
     */
    public function __construct(
        private ConnectorRepository $connectorRepository,
        private DataSource $dataSource,
    ) {
    }

    /**
     * Retrieve a config for a given instance.
     *
     * @param string $instanceId
     * @return IConfigLoader
     * @throws OutOfBoundsException if `$instanceId` is unknown
     */
    public function fetchConfigForInstance(string $instanceId): IConfigLoader
    {
        $instanceConnector = $this->connectorRepository->fetchConnectorForInstance($instanceId);
        if (!isset($this->instanceConfigs[$instanceId])) {
            $this->instanceConfigs[$instanceId] = $this->dataSource->findAllConfigs($instanceConnector);
        }
        return $this->instanceConfigs[$instanceId];
    }

    /**
     * Invalidate the configuration for a given instance: the next time this
     * instance's config is fetched, it will be reloaded.
     *
     * @param string $instanceId
     * @return void
     */
    public function invalidateConfigForInstance(string $instanceId): void
    {
        unset($this->instanceConfigs[$instanceId]);
    }
}
