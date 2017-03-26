<?php

/**
 * Factory to produce database connector
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database;

use Alaress\Database\Connector\Db2\Connector as Db2Connector;
use Alaress\Database\Connector\Mysql\Connector as MysqlConnector;
use Alaress\Database\Connector\Sqlsrv\Connector as SqlsrvConnector;

class Factory
{
    /**
     * Create a MySQL connector
     *
     * @return MysqlConnector
     */
    public function createMysql()
    {
        return new MysqlConnector();
    }

    /**
     * Create an MSSQL connector
     *
     * @return SqlsrvConnector
     */
    public function createSqlsrv()
    {
        return new SqlsrvConnector();
    }

    /**
     * Create a DB2 connector
     *
     * @return Db2Connector
     */
    public function createDb2()
    {
        return new Db2Connector();
    }
}