<?php

/**
 * Factory to produce database connector
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Database;

use Alaress\Database\Broker\Dblib\Broker as DblibBroker;
use Alaress\Database\Broker\Db2\Broker as Db2Broker;
use Alaress\Database\Broker\Mysql\Broker as MysqlBroker;
use Alaress\Database\Broker\Sqlsrv\Broker as SqlsrvBroker;
use Alaress\Database\Connector\Base as Connector;
use Alaress\Database\Transactor\Base as Transactor;
use Alaress\Database\Transactor\Strategy;
use InvalidArgumentException;

use function in_array;

class Factory
{

    public const PDO_DRIVER_DBLIB = 'dblib';
    public const PDO_DRIVER_SQLSRV = 'sqlsrv';


    /**
     * Create a MySQL connector
     *
     * @return Connector
     */
    public function createMysql()
    {
        $broker = new MysqlBroker();
        return new Connector($broker, new Transactor(new Strategy\Savepoint()));
    }


    /**
     * Create a MSSQL connector (SQLSRV, or DBLIB if that's not available)
     *
     * @param string $preferredMsSqlServerDriver
     * @return Connector
     * @throws InvalidArgumentException
     */
    public function createMssql(string $preferredMsSqlServerDriver): Connector
    {
        $validDrivers = [
            self::PDO_DRIVER_DBLIB,
            self::PDO_DRIVER_SQLSRV,
        ];
        if (!in_array($preferredMsSqlServerDriver, $validDrivers, true)) {
            throw new InvalidArgumentException("Unrecognised preferred MS SQL Server driver: '$preferredMsSqlServerDriver'");
        }

        if ($preferredMsSqlServerDriver === self::PDO_DRIVER_SQLSRV && SqlsrvBroker::isAvailable()) {
            return $this->createSqlsrv();
        }
        return $this->createDblib();
    }


    /**
     * Create a DBLIB connector
     *
     * @deprecated use the SQLSRV connector (via `createSqlsrv`) instead
     *
     * @return Connector
     */
    public function createDblib()
    {
        $broker = new DblibBroker();
        return new Connector($broker, new Transactor(new Strategy\Nested()));
    }

    /**
     * Create a SQLSRV connector
     *
     * @return Connector
     */
    public function createSqlsrv()
    {
        $broker = new SqlsrvBroker();
        return new Connector($broker, new Transactor(new Strategy\Nested()));
    }

    /**
     * Create a DB2 connector
     *
     * @return Connector
     */
    public function createDb2()
    {
        $broker = new Db2Broker();
        return new Connector($broker, new Transactor(new Strategy\Savepoint()));
    }
}
