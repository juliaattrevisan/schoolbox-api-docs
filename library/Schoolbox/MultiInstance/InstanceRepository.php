<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\MultiInstance;

use OutOfBoundsException;

/**
 * Repository of all instances this node knows about.
 *
 * @template TInstanceConfigArray as array{
 *     hostnames: list<string>,
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
class InstanceRepository
{
    /**
     * A cache of parsed instance configs, stored by instance.
     *
     * @var array<string, InstanceConfig>
     */
    private array $instanceConfigs = [];

    /**
     * Constructor
     *
     * @param array<array-key, TInstanceConfigArray> $instances A list of instances, as parsed from an .ini file.
     *                                                          The key is usually a string, but in rare cases (i.e. an
     *                                                          instance ID which is wholly numeric) may be an integer.
     */
    public function __construct(private array $instances)
    {
    }

    /**
     * Return the instance data for a given instance.
     *
     * @param string $instanceId
     * @return InstanceConfig
     * @throws OutOfBoundsException if `$instanceId` is unknown
     */
    public function fetchConfigForInstance(string $instanceId): InstanceConfig
    {
        if (!isset($this->instances[$instanceId])) {
            throw new OutOfBoundsException(sprintf('Unknown instance ID "%s"', $instanceId));
        }
        $instanceConfigArray = $this->instances[$instanceId];
        $this->instanceConfigs[$instanceId] ??= new InstanceConfig(
            $instanceId,
            $instanceConfigArray['hostnames'],
            $instanceConfigArray['db_host'],
            $instanceConfigArray['db_schema'],
            $instanceConfigArray['db_user'],
            $instanceConfigArray['db_pass'],
            $instanceConfigArray['db_host_read'] ?? null,
            $instanceConfigArray['db_schema_read'] ?? null,
            $instanceConfigArray['db_user_read'] ?? null,
            $instanceConfigArray['db_pass_read'] ?? null,
        );
        return $this->instanceConfigs[$instanceId];
    }

    /**
     * Fetch an instance configuration, given a hostname.
     *
     * @param string $hostname
     * @return InstanceConfig
     * @throws OutOfBoundsException if no config matches this hostname
     */
    public function fetchConfigForHostname(string $hostname): InstanceConfig
    {
        foreach ($this->instances as $instanceId => $instance) {
            $instanceConfig = $this->fetchConfigForInstance((string)$instanceId);
            if (in_array($hostname, $instanceConfig->hostnames)) {
                return $instanceConfig;
            }
        }
        throw new OutOfBoundsException(sprintf('Unknown hostname "%s"', $hostname));
    }
}
