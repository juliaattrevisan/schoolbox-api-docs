<?php

/**
 * Factory to produce database connector
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database;

use Alaress\Database\Broker\Dblib\Broker as DblibBroker;
use Alaress\Database\Broker\Db2\Broker as Db2Broker;
use Alaress\Database\Broker\Mysql\Broker as MysqlBroker;
use Alaress\Database\Broker\Sqlsrv\Broker as SqlsrvBroker;
use Alaress\Database\Connector\Base as Connector;

class Factory
{
    /**
     * Create a MySQL connector
     *
     * @return Connector
     */
    public function createMysql()
    {
        return new Connector(
            new MysqlBroker()
        );
    }

    /**
     * Create a DBLIB connector
     *
     * @return Connector
     */
    public function createDblib()
    {
        return new Connector(
            new DblibBroker()
        );
    }

    /**
     * Create an MSSQL connector
     *
     * @return Connector
     */
    public function createSqlsrv()
    {
        return new Connector(
            new SqlsrvBroker()
        );
    }

    /**
     * Create a DB2 connector
     *
     * @return Connector
     */
    public function createDb2()
    {
        return new Connector(
            new Db2Broker()
        );
    }
}