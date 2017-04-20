<?php
/**
 * Provides the common connection functionality to the Edumate connection classes
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Alaress\Database\Connector\Base as Connector;
use Alaress\Database\Factory as DatabaseFactory;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;

class External_Edumate_Connection
{
    /** @var Group $groupHandler */
    private $groupHandler;

    /** @var User $userHandler */
    private $userHandler;

    /** @var Connector $connector */
    protected $connector;

    /** @var IConfigLoader $config */
    protected $config;

    /**
     * Constructor
     */
    public function __construct()
    {
        $factory = new DatabaseFactory();
        $this->connector = $factory->createMssql();
        $this->config = ConfigMapper::getInstance();

        $this->connector->connect(
            $this->config->get('external_db_host'),
            $this->config->get('external_db_db'),
            $this->config->get('external_db_user'),
            $this->config->get('external_db_passwd')
        );

        $this->setGroupHandler(new Group());
        $this->setUserHandler(new User());
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
        return $this->getConnection();
    }

    /**
     * Set the group handler
     * @param Group $group
     */
    protected function setGroupHandler($group)
    {
        $this->groupHandler = $group;
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
     * Set the user handler
     * @param $user
     */
    protected function setUserHandler($user)
    {
        $this->userHandler = $user;
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
