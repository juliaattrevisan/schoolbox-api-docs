<?php

namespace Schoolbox\Database;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Connector\NullConnector;
use Alaress\Database\Exception as DatabaseException;
use Alaress\Database\Factory as DatabaseFactory;
use InvalidArgumentException;
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
    private static function getInstance(): Factory
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
     * @return null|IConnector
     * @throws DatabaseException
     */
    public static function getInstanceExternal(): ?IConnector
    {
        // TODO SA-8354: use DI'd database instead
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
     * @return null|IConnector
     * @throws DatabaseException
     */
    public static function getInstanceInternal(): ?IConnector
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
     * @throws DatabaseException
     */
    public function getInternalDatabase(): IConnector
    {
        $connectorInternal = $this->factory->createMysql();

        if (!$connectorInternal->connect(SQL_host, SQL_db, SQL_user, SQL_passwd)) {
            throw new DatabaseException("Unable to connect to Schoolbox database.");
        }

        $connectorInternal->setTimezone($this->fetchTimezone($connectorInternal));
        $connectorInternal->executeQuery("SET SESSION max_statement_time = CAST(? AS INT)", [SQL_MAX_STATEMENT_TIME_SECONDS]);

        return $connectorInternal;
    }


    /**
     * Retrieve an appropriate external database connection based on the 'external_type' set in Schoolbox configuration
     *
     * @param IConfigLoader $config         Schoolbox configuration
     * @param bool          $ignoreDisabled If TRUE, always try to return an external connection
     * @return null|IConnector Return the external connector appropriate for the SIS
     * @throws DatabaseException
     * @throws InvalidArgumentException
     */
    public function getExternalDatabase(IConfigLoader $config, bool $ignoreDisabled = false): ?IConnector
    {
        if (!$ignoreDisabled && !$config->get('external_enable')) {
            return new NullConnector();
        }

        switch ($config->get('external_type')) {
            case null:
                return new NullConnector();

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
            case 'schooledge':
                $connectorExternal = $this->factory->createSqlsrv();
                break;

            // MySQL
            case 'denbigh':
                $connectorExternal = $this->factory->createMysql();
                break;

            // IBM DB2
            case 'edumate':
                $connectorExternal = $this->factory->createDb2();
                [$host, $schema] = explode('/', $config->get('external_db_host'), 2);
                $connectorExternal->connect(
                    $host,
                    $config->get('external_db_db'),
                    $config->get('external_db_user'),
                    $config->get('external_db_passwd'),
                    ['schema' => $schema]
                );
                return $connectorExternal;
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
     * @throws DatabaseException
     */
    private function fetchTimezone(IConnector $connectorInternal): string
    {
        $statement = $connectorInternal->prepareQuery("SELECT `value` FROM `config` WHERE name LIKE 'date_timezone'");
        $statement->execute();
        return $statement->fetchColumn();
    }
}
