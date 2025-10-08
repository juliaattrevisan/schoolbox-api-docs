<?php

/**
 * Factory to produce database connector
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Database;

use Alaress\Database\Broker\Db2\Broker as Db2Broker;
use Alaress\Database\Broker\Mysql\Broker as MysqlBroker;
use Alaress\Database\Broker\Sqlsrv\Broker as SqlsrvBroker;
use Alaress\Database\Connector\Base as Connector;
use Alaress\Database\Quoter\Db2\Quoter as Db2Quoter;
use Alaress\Database\Quoter\Mysql\Quoter as MysqlQuoter;
use Alaress\Database\Quoter\Sqlsrv\Quoter as SqlsrvQuoter;
use Alaress\Database\Transactor\Base as Transactor;
use Alaress\Database\Transactor\Strategy;

class Factory
{
    public const PDO_DRIVER_SQLSRV = 'sqlsrv';


    /**
     * Create a MySQL connector
     *
     * @return Connector
     */
    public function createMysql()
    {
        return new Connector(new MysqlBroker(), new Transactor(new Strategy\Savepoint()), new MysqlQuoter());
    }

    /**
     * Create a SQLSRV connector
     *
     * @return Connector
     */
    public function createSqlsrv()
    {
        return new Connector(new SqlsrvBroker(), new Transactor(new Strategy\Nested()), new SqlsrvQuoter());
    }

    /**
     * Create a DB2 connector
     *
     * @return Connector
     */
    public function createDb2()
    {
        return new Connector(new Db2Broker(), new Transactor(new Strategy\Nested()), new Db2Quoter());
    }
}
