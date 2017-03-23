<?php

/**
 * Factory to produce database connector
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Connector\Mysql\Connector as MysqlConnector;
use Alaress\Database\Connector\Sqlsrv\Connector as SqlsrvConnector;

class Factory
{
    /**
     * @return MysqlConnector
     */
    public static function CreateMysql()
    {
        return new MysqlConnector();
    }

    /**
     * @return SqlsrvConnector
     */
    public static function CreateSqlsrv()
    {
        return new SqlsrvConnector();
    }

    /**
     * TODO: Implement IBM DB2 connector classes
     */
    public static function CreateDb2()
    {
        throw new Exception("DB2 connector not yet implemented");
        return null;
    }
}