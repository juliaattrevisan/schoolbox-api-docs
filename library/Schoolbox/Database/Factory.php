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
     * Singleton instance of the internal database connection within this
     * factory instance.
     */
    private ?IConnector $internalDatabase = null;

    /**
     * Singleton instance of the read replica internal database connection
     * within this factory instance.
     */
    private ?IConnector $internalReadDatabase = null;

    /**
     * Singleton instance of the master (writable) internal database connection
     * within this factory instance.
     */
    private ?IConnector $internalWriteDatabase = null;

    /**
     * Singleton instance of the external (SIS or SUDS) database connection
     * within this factory instance.
     */
    private ?IConnector $externalDatabase = null;

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
     * @deprecated use non-static `getExternalDatabase` instead
     * @return null|IConnector
     * @throws DatabaseException
     */
    public static function getInstanceExternal(): ?IConnector
    {
        return self::getInstance()->getExternalDatabase(new Loader());
    }


    /**
     * Retrieves static instance of internal database connection.
     * TODO: Once we've got DI accessible everywhere this is currently used this should no longer required
     *
     * @deprecated use non-static `getInternalDatabase` instead
     * @return IConnector
     * @throws DatabaseException
     */
    public static function getInstanceInternal(): IConnector
    {
        return self::getInstance()->getInternalDatabase();
    }

    /**
     * Retrieves static instance of internal Read only database connection.
     *
     * @deprecated use non-static `getInternalReadDatabase` instead
     * @return IConnector
     * @throws DatabaseException
     */
    public static function getInstanceInternalRead(): IConnector
    {
        return self::getInstance()->getInternalReadDatabase();
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
        $this->internalDatabase ??= new ReadReplicatedProxy(
            $this->getInternalWriteDatabase(),
            $this->getInternalReadDatabase(),
            10,
        );
        return $this->internalDatabase;
    }

    /**
     * Retrieve a writable connection to Schoolbox database
     *
     * @return IConnector
     * @throws DatabaseException
     */
    public function getInternalWriteDatabase(): IConnector
    {
        if ($this->internalWriteDatabase) {
            return $this->internalWriteDatabase;
        }

        $this->internalWriteDatabase = $this->factory->createMysql();

        $host = SQL_host;
        $db   = SQL_db;
        $user = SQL_user;
        $pass = SQL_passwd;

        /** @phpstan-ignore-next-line - these values are not always defined */
        if (!isset($host, $db, $user, $pass)) {
            throw new DatabaseException("Schoolbox database not configured.");
        }
        if (!$this->internalWriteDatabase->connect($host, $db, $user, $pass)) {
            throw new DatabaseException("Unable to connect to Schoolbox database.");
        }

        $this->internalWriteDatabase->setTimezone($this->fetchTimezone($this->internalWriteDatabase));

        return $this->internalWriteDatabase;
    }

    /**
     * Retrieve a connection to Read only Schoolbox database
     *
     * @return IConnector
     * @throws DatabaseException
     */
    public function getInternalReadDatabase(): IConnector
    {
        if ($this->internalReadDatabase) {
            return $this->internalReadDatabase;
        }

        $this->internalReadDatabase = $this->factory->createMysql();

        $hostRead = SQL_host_read;
        $dbRead   = SQL_db_read;
        $userRead = SQL_user_read;
        $passRead = SQL_passwd_read;

        /** @phpstan-ignore-next-line - these values are not always defined */
        if (!isset($hostRead, $dbRead, $userRead, $passRead)) {
            throw new DatabaseException("Schoolbox read-only database not configured.");
        }
        if (!$this->internalReadDatabase->connect($hostRead, $dbRead, $userRead, $passRead)) {
            throw new DatabaseException("Unable to connect to Schoolbox read-only database.");
        }

        $this->internalReadDatabase->setTimezone($this->fetchTimezone($this->internalReadDatabase));

        return $this->internalReadDatabase;
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
        if ($this->externalDatabase) {
            return $this->externalDatabase;
        }

        if (!$ignoreDisabled && !$config->get('external_enable')) {
            $this->externalDatabase = new NullConnector();
            return $this->externalDatabase;
        }

        switch ($config->get('external_type')) {
            case null:
                $this->externalDatabase = new NullConnector();
                return $this->externalDatabase;

            // Schoolbox connector: just return internal DB
            case 'schoolbox':
                $this->externalDatabase = $this->getInternalDatabase();
                return $this->externalDatabase;

            // Microsoft SQL Server
            case 'tass':
            case 'synergetic':
            case 'schoolpro':
            case 'pcschools':
            case 'isams':
            case 'doublefirst':
            case 'schooledge':
                $this->externalDatabase = $this->factory->createSqlsrv();
                break;

            // IBM DB2
            case 'edumate':
                $this->externalDatabase = $this->factory->createDb2();
                [$host, $schema] = explode('/', $config->get('external_db_host'), 2);
                $this->externalDatabase->connect(
                    $host,
                    $config->get('external_db_db'),
                    $config->get('external_db_user'),
                    $config->get('external_db_passwd'),
                    ['schema' => $schema]
                );
                return $this->externalDatabase;

            default:
                throw new DatabaseException("Unknown external database type.");
        }

        try {
            $this->externalDatabase->connect(
                $config->get('external_db_host'),
                $config->get('external_db_db'),
                $config->get('external_db_user'),
                $config->get('external_db_passwd'),
                ['Encrypt' => $config->get('external_db_encryption')]
            );

            $this->externalDatabase->setTimezone($this->fetchTimezone($this->getInternalReadDatabase()));

            return $this->externalDatabase;
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
        $this->externalDatabase = new NullConnector();
        return $this->externalDatabase;
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
