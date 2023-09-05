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

class ConnectorRepository
{
    /**
     * Instance connectors already created.
     *
     * @var array<string, IConnector>
     */
    private array $instanceConnectors = [];

    /**
     * Constructor
     *
     * @param InstanceRepository $instanceRepository Repository of instance configurations.
     * @param DatabaseFactory    $databaseFactory    Factory for creating database connections.
     */
    public function __construct(
        private InstanceRepository $instanceRepository,
        private DatabaseFactory $databaseFactory,
    ) {
    }

    /**
     * Retrieve a database connection for a given instance.
     *
     * @param string $instanceId
     * @return IConnector
     * @throws OutOfBoundsException if `$instanceId` is unknown
     */
    public function fetchDatabaseForInstance(string $instanceId): IConnector
    {
        $instanceConfig = $this->instanceRepository->fetchConfigForInstance($instanceId);

        // Build a connection if required: assuming read-only for now
        if (!isset($this->instanceConnectors[$instanceId])) {
            $this->instanceConnectors[$instanceId] = $this->databaseFactory->createMysql();
            $this->instanceConnectors[$instanceId]->connect(
                $instanceConfig->dbHost,
                $instanceConfig->dbSchema,
                $instanceConfig->dbUser,
                $instanceConfig->dbPassword,
            );
        }
        return $this->instanceConnectors[$instanceId];
    }
}
