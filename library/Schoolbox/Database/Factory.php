<?php

namespace Schoolbox\Database;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Connector\NullConnector;
use Alaress\Database\Connector\ReadReplicatedProxy;
use Alaress\Database\Exception;
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
     * Read Only DB
     *
     * @return IConnector
     */
    protected static $connectorInternalRead;

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

        return (self::$connectorExternal instanceof NullConnector ? null : self::$connectorExternal);
    }


    /**
     * Retrieves static instance of internal database connection.
     * TODO: Once we've got DI accessible everywhere this is currently used this should no longer required
     *
     * @deprecated
     * @return IConnector
     * @throws DatabaseException
     */
    public static function getInstanceInternal(): IConnector
    {
        if (!isset(self::$connectorInternal)) {
            self::$connectorInternal = self::getInstance()->getInternalDatabase();
        }

        return self::$connectorInternal;
    }

    /**
     * Retrieves static instance of internal Read only database connection.
     *
     * @return IConnector
     * @throws DatabaseException
     */
    public static function getInstanceInternalRead(): IConnector
    {
        if (!isset(self::$connectorInternalRead)) {
            self::$connectorInternalRead = self::getInstance()->getInternalReadDatabase();
        }

        return self::$connectorInternalRead;
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
        // TODO: use the DB Proxy to correctly route to the correct DB connection...
        return new ReadReplicatedProxy($this->getInternalWriteDatabase(), $this->getInternalReadDatabase(), 10);
    }

    /**
     * Retrieve a writable connection to Schoolbox database
     *
     * @return IConnector
     * @throws DatabaseException
     */
    public function getInternalWriteDatabase(): IConnector
    {
        // TODO: use the DB Proxy to correctly route to the correct DB connection...
        $connectorInternal = $this->factory->createMysql();

        $host = SQL_host;
        $db   = SQL_db;
        $user = SQL_user;
        $pass = SQL_passwd;

        /** @phpstan-ignore-next-line - these values are not always defined */
        if (!isset($host, $db, $user, $pass)) {
            throw new DatabaseException("Schoolbox database not configured.");
        }
        if (!$connectorInternal->connect($host, $db, $user, $pass)) {
            throw new DatabaseException("Unable to connect to Schoolbox database.");
        }

        $connectorInternal->setTimezone($this->fetchTimezone($connectorInternal));

        return $connectorInternal;
    }

    /**
     * Retrieve a connection to Read only Schoolbox database
     *
     * @return IConnector
     * @throws DatabaseException
     */
    public function getInternalReadDatabase(): IConnector
    {
        $connectorInternalRead = $this->factory->createMysql();

        $hostRead = SQL_host_read;
        $dbRead   = SQL_db_read;
        $userRead = SQL_user_read;
        $passRead = SQL_passwd_read;

        /** @phpstan-ignore-next-line - these values are not always defined */
        if (!isset($hostRead, $dbRead, $userRead, $passRead)) {
            throw new DatabaseException("Schoolbox read-only database not configured.");
        }
        if (!$connectorInternalRead->connect($hostRead, $dbRead, $userRead, $passRead)) {
            throw new DatabaseException("Unable to connect to Schoolbox read-only database.");
        }

        $connectorInternalRead->setTimezone($this->fetchTimezone($connectorInternalRead));

        return $connectorInternalRead;
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
            case 'isams':
            case 'doublefirst':
            case 'schooledge':
                $connectorExternal = $this->factory->createSqlsrv();
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

        try {
            $connectorExternal->connect(
                $config->get('external_db_host'),
                $config->get('external_db_db'),
                $config->get('external_db_user'),
                $config->get('external_db_passwd'),
                ['Encrypt' => $config->get('external_db_encryption')]
            );

            $connectorExternal->setTimezone($this->fetchTimezone($this->getInternalReadDatabase()));

            return $connectorExternal;
        } catch (Exception $e) {
            /* If the connectivity doesn't work for some throwable reason, you'll find it silently discarded
             * here, which is annoying. This makes a NullConnector which, for some usages of the external db,
             * causes it to successfully fail silently. It's not logged anywhere.
             *
                string(241) "Error connecting to database.
                DSN String: sqlsrv:Server=172.31.15.10;Database=Synergetic_AUVIC_ALARESS_PRD;LoginTimeout=2;MultipleActiveResultSets=0;
                PDO Error: SQLSTATE[HYT00]: [Microsoft][ODBC Driver 17 for SQL Server]Login timeout expired"
             */
            // var_dump($e->getMessage());
        }
        return new NullConnector();
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
