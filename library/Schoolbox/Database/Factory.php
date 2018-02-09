<?php

namespace Schoolbox\Database;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Exception as DatabaseException;
use Alaress\Database\Factory as DatabaseFactory;
use Schoolbox\Config\Loader;
use Schoolbox\IConfigLoader;

class Factory
{
    /**
     * @var DatabaseFactory $factory
     */
    private $factory;

    /**
     * TODO: Once we've got DI accessible everywhere this is currently used this should no longer required
     *
     * @deprecated
     * @return IConnector
     */
    protected static $connectorExternal;

    /**
     * TODO: Once we've got DI accessible everywhere this is currently used this should no longer required
     *
     * @deprecated
     * @return IConnector
     */
    protected static $connectorInternal;

    /**
     * An instance of this object
     *
     * @deprecated
     * @var self
     */
    private static $instance;

    /**
     * Retrieve a static instance of this factory.
     * TODO: Once we've got DI accessible everywhere this is currently used this should no longer required
     *
     * @deprecated
     * @return self
     */
    private static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self(new DatabaseFactory());
        }

        return self::$instance;
    }

    /**
     * Retrieves static instance of external database connection (used in External connectors)
     * TODO: Once we've got DI accessible everywhere this is currently used this should no longer required
     *
     * @deprecated
     * @return IConnector
     */
    public static function getInstanceExternal()
    {
        if (!isset(self::$connectorExternal)) {
            self::$connectorExternal = self::getInstance()->getExternalDatabase(new Loader());
        }

        return self::$connectorExternal;
    }

    /**
     * Retrieves static instance of internal database connection (used in Denbigh)
     * TODO: Once we've got DI accessible everywhere this is currently used this should no longer required
     *
     * @deprecated
     * @return IConnector
     */
    public static function getInstanceInternal()
    {
        if (!isset(self::$connectorInternal)) {
            self::$connectorInternal = self::getInstance()->getInternalDatabase();
        }

        return self::$connectorInternal;
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
     *
     * @return IConnector
     */
    public function getInternalDatabase()
    {
        $connectorInternal = $this->factory->createMysql();

        if (!$connectorInternal->connect(SQL_host, SQL_db, SQL_user, SQL_passwd)) {
            throw new DatabaseException("Unable to connect to Schoolbox database.");
        }

        $connectorInternal->setTimezone($this->fetchTimezone($connectorInternal));

        return $connectorInternal;
    }

    /**
     * Retrieve an appropriate external database connection based on the 'external_type' set in Schoolbox configuration
     *
     * @param IConfigLoader $config Schoolbox configuration
     *
     * @return IConnector Return the external connector appropriate for the SIS
     */
    public function getExternalDatabase(IConfigLoader $config)
    {
        switch ($config->get('external_type')) {

            // Schoolbox connector: just return internal DB
            case 'schoolbox':
                return $this->getInternalDatabase();

            // Microsoft SQL Server
            case 'tass':
            case 'synergetic':
            case 'schoolpro':
            case 'pcschools':
            case 'maze':
            case 'isams':
            case 'doublefirst':
            case 'humanedge':
            case 'schooledge':
                $connectorExternal = $this->factory->createMssql();
                break;

            // MySQL
            case 'denbigh':
                $connectorExternal = $this->factory->createMysql();
                break;

            // IBM DB2
            case 'edumate':
                $connectorExternal = $this->factory->createDb2();
                break;
        }

        if (!isset($connectorExternal)) {
            throw new DatabaseException("Unknown external database type.");
        }

        $connectorExternal->connect(
            $config->get('external_db_host'),
            $config->get('external_db_db'),
            $config->get('external_db_user'),
            $config->get('external_db_passwd')
        );

        $connectorExternal->setTimezone($this->fetchTimezone($this->getInternalDatabase()));

        return $connectorExternal;
    }

    /**
     * Fetch the timezone from the database we are connecting to
     *
     * @param IConnector $connectorInternal
     *
     * @return string Timezone
     */
    private function fetchTimezone(IConnector $connectorInternal)
    {
        $statement = $connectorInternal->prepareQuery("SELECT `value` FROM `config` WHERE name LIKE 'date_timezone'");
        $statement->execute();
        return $statement->fetchColumn();
    }
}
