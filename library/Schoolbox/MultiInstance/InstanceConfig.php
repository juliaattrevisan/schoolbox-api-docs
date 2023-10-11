<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\MultiInstance;

use SensitiveParameter;

/**
 * Data object for instance configurations, as stored in the instance config
 * file for this node.
 *
 * This stores only the basics required to connect to an instance:
 * * the instance's ID
 * * hostnames from which the instance is served
 * * the database connection details
 *
 * All other configuration must be fetched from the config loader.
 */
class InstanceConfig
{
    /**
     * Constructor
     *
     * @param string        $instanceId     The instance ID for this config.
     * @param array<string> $hostnames      The hostnames from which this instance may be served.
     * @param string        $dbHost         The instance's database connection host.
     * @param string        $dbSchema       The instance's database connection schema/database.
     * @param string        $dbUser         The instance's database connection username.
     * @param string        $dbPassword     The instance's database connection password.
     * @param string|null   $dbHostRead     The instance's read-only database connection host, if provided.
     * @param string|null   $dbSchemaRead   The instance's read-only database connection schema/database, if provided.
     * @param string|null   $dbUserRead     The instance's read-only database connection username, if provided.
     * @param string|null   $dbPasswordRead The instance's read-only database connection password, if provided.
     */
    public function __construct(
        public string $instanceId,
        public array $hostnames,
        public string $dbHost,
        public string $dbSchema,
        public string $dbUser,
        #[SensitiveParameter] public string $dbPassword,
        public ?string $dbHostRead = null,
        public ?string $dbSchemaRead = null,
        public ?string $dbUserRead = null,
        #[SensitiveParameter] public ?string $dbPasswordRead = null,
    ) {
    }

    /**
     * Debug info: exclude DB passwords
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return [
            'instanceId'     => $this->instanceId,
            'hostnames'      => $this->hostnames,
            'dbHost'         => $this->dbHost,
            'dbSchema'       => $this->dbSchema,
            'dbUser'         => $this->dbUser,
            'dbPassword'     => '',
            'dbHostRead'     => $this->dbHostRead,
            'dbSchemaRead'   => $this->dbSchemaRead,
            'dbUserRead'     => $this->dbUserRead,
            'dbPasswordRead' => isset($this->dbPasswordRead) ? '' : null,
        ];
    }
}
