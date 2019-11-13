<?php
/**
 * Provides the common connection functionality to the Edumate connection classes
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Database\Connector\Base as Connector;
use Alaress\Database\Factory as DatabaseFactory;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\IConfigLoader;

class External_Edumate_Connection
{
    /** @var DatabaseFactory */
    private $databaseFactory;

    /** @var Group $groupHandler */
    private $groupHandler;

    /** @var User $userHandler */
    private $userHandler;

    /** @var Connector $connector */
    private $connector;

    /** @var IConfigLoader $config */
    private $config;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->databaseFactory = new DatabaseFactory();
        $this->config          = ConfigLoader::getInstance();
        $this->connect();
        $this->groupHandler = new Group();
        $this->userHandler  = new User();
    }


    protected function connect(): void
    {
        $this->connector = $this->databaseFactory->createDb2();

        $this->connector->connect(
            $this->config->get('external_db_host'),
            $this->config->get('external_db_db'),
            $this->config->get('external_db_user'),
            $this->config->get('external_db_passwd')
        );
    }

    /**
     * Internal DB2-specific code to turn CAPITALISEDFIELDS into NotCapitalisedFields
     *
     * @param array $record
     * @param array $fieldNames
     * @return array
     */
    protected function normaliseFields(array $record, array $fieldNames)
    {
        foreach ($fieldNames as $field) {
            $upper = strtoupper($field);
            if (array_key_exists($upper, $record)) {
                $record[$field] = $record[$upper];
                unset($record[$upper]);
            }
        }
        return $record;
    }

    // Getters & Setters

    /**
     * @return Connector
     */
    protected function getConnection()
    {
        return $this->connector;
    }


    /**
     * Retrieve the group handler
     * @return Group
     */
    protected function getGroupHandler()
    {
        return $this->groupHandler;
    }


    /**
     * Retrieve the user handler
     * @return User
     */
    protected function getUserHandler()
    {
        return $this->userHandler;
    }

    /**
     * Quote input for safety
     *
     * @param $value string
     * @return string
     */
    public function quote($value) {
        return db2_escape_string($value);
    }
}
