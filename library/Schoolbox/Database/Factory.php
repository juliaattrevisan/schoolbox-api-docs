<?php

namespace Schoolbox\Database;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Exception as DatabaseException;
use Alaress\Database\Factory as DatabaseFactory;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;

class Factory
{
    /**
     * @var DatabaseFactory $factory
     */
    private $factory;

    /**
     * @return IConnector
     */
    protected static $connectorExternal;

    /**
     * Retrieves static instance of external database connection
     *
     * @return IConnector
     */
    public static function getInstanceExternal() {
        if (!isset(self::$connectorExternal)) {
            $factory = new DatabaseFactory();
            self::$connectorExternal = $factory->createSqlsrv();
            $config = ConfigMapper::getInstance();

            self::$connectorExternal->connect(
                $config->get('external_db_host'),
                $config->get('external_db_db'),
                $config->get('external_db_user'),
                $config->get('external_db_passwd')
            );
        }
        return self::$connectorExternal;
    }

    /**
     * Factory Constructor
     *
     * @param DatabaseFactory $factory
     */
    public function __construct($factory)
    {
        $this->factory = $factory;
    }

    /**
     * Retrieve a connection to Schoolbox database
     */
    public function getInternalDatabase()
    {
        $connectorInternal = $this->factory->createMysql();

        if (!$connectorInternal->connect(SQL_host, SQL_db, SQL_user, SQL_passwd)) {
            throw new DatabaseException("Unable to connect to Schoolbox database.");
        }

        return $connectorInternal;
    }

    /**
     * Retrieve an appropriate external database connection based on the 'external_type' set in Schoolbox configuration
     *
     * @param IConfigLoader $config Schoolbox configuration
     * @return IConnector|IConnector[] Return the external connector/connectors appropriate for the SIS
     */
    public function getExternalDatabase($config)
    {
        switch ($config['external_type']) {

            // Microsoft SQL Server

            case 'tass':
            case 'synergetic':
            case 'schoolpro':
            case 'pcschools':
            case 'maze':
            case 'isams':
            case 'doublefirst':
            case 'humanedge':

                $connectorExternal = $this->factory->createSqlsrv();
                break;

            // MySQL

            case 'schoolbox':
            case 'denbigh':

                $connectorExternal = $this->factory->createMysql();
                break;

            // IBM DB2

            case 'edumate':

                $connectorExternal = $this->factory->createDb2();
                break;
        };

        if (!isset($connectorExternal)) {
            throw new DatabaseException("Unknown external database type.");
        }

        if (!$connectorExternal->connect(
            $config['external_db_host'],
            $config['external_db_db'],
            $config['external_db_user'],
            $config['external_db_passwd']
        )) {
            throw new DatabaseException("Unable to connect to {$config['external_type']} database.");
        }

        // HumanEdge is a special little potato with two databases

        if ($config['external_type'] == 'humanedge') {

            $connectorAplus = $this->factory->createSqlsrv();

            if (!$connectorAplus->connect(
                $config['external_humanedge_aplus_db_host'],
                $config['external_humanedge_aplus_db_db'],
                $config['external_humanedge_aplus_db_user'],
                $config['external_humanedge_aplus_db_passwd']
            )) {
                throw new DatabaseException("Unable to connect to HumanEdge A+ database.");
            }

            // Return the connectors in an array

            $connectors = [];
            $connectors['external'] = $connectorExternal;
            $connectors['aplus'] = $connectorAplus;
            return $connectors;
        }

        // Everything else just gets one connector

        return $connectorExternal;
    }
}
