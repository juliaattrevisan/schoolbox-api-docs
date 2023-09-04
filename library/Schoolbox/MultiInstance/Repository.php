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
use Schoolbox\Config\TestLoader;
use Schoolbox\IConfigLoader;

class Repository
{
    public const FIELD_BOOLEAN       =  3;
    public const FIELD_CHECKBOX      =  5;

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

    public function __construct(
        private array $instances,
        private DatabaseFactory $factory
    ) {
    }


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

    public function getConfigForInstance(string $instanceId): IConfigLoader
    {
        if (!isset($this->instanceConfigs[$instanceId])) {
            $this->instanceConfigs[$instanceId] = new TestLoader($this->loadConfig($instanceId));
        }
        return $this->instanceConfigs[$instanceId];
    }

    private function getInstanceData(string $instanceId): array
    {
        if (!isset($this->instances[$instanceId])) {
            throw new OutOfBoundsException(sprintf('Unknown instance ID "%s"', $instanceId));
        }
        return $this->instances[$instanceId];
    }

    private function loadConfig(string $instanceId): array
    {
        $database = $this->getDatabaseForInstance($instanceId);
        $query    = $database->executeQuery('SELECT name, value, field_id FROM config');
        $configs  = [];
        foreach ($query as $row) {
            $configs[$row['name']] = $this->hydrateValue((int)$row['field_id'], $row['value']);
        }
        return $configs;
    }

    /**
     * Light-weight hydration to respect boolean fields
     *
     * @param int   $fieldId
     * @param mixed $value
     * @return mixed
     */
    private function hydrateValue(int $fieldId, $value)
    {
        switch ($fieldId) {
            case self::FIELD_BOOLEAN:
            case self::FIELD_CHECKBOX:
                return $value === null ? null : (bool)$value;
            default:
        }
        return $value;
    }
}
