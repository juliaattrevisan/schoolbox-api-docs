<?php

/**
 * MySQL Database connection broker
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Broker\Mysql;

use Alaress\Database\Broker\Base;
use Alaress\Database\Broker\IBroker;
use Alaress\Database\Iterator\Mysql\Iterator;
use Alaress\Database\Quoter\Mysql\Quoter as MysqlQuoter;
use Alaress\Database\Statement\FunctionCall\Mysql\FunctionCall;
use Alaress\Database\Statement\StoredProcedure\Mysql\StoredProcedure;
use PDO;

class Broker extends Base implements IBroker
{
    // These return MySQL specific statement objects

    /**
     * @inheritdoc
     */
    public static function isAvailable()
    {
        return in_array('mysql', PDO::getAvailableDrivers(), true);
    }

    /**
     * @inheritdoc
     */
    protected function buildQuoter()
    {
        return new MysqlQuoter();
    }

    /**
     * @inheritdoc
     */
    public function prepareFunctionCall($function)
    {
        return new FunctionCall($this, $function);
    }

    /**
     * @inheritdoc
     */
    public function prepareStoredProcedure($procedure)
    {
        return new StoredProcedure($this, $procedure);
    }

    /**
     * @inheritdoc
     */
    public function getIterator($statement, $pdoStatement)
    {
        return new Iterator($this, $statement, $pdoStatement);
    }

    /**
     * @inheritdoc
     */
    public function getDsn() {
        $portString = '';

        if (!empty($this->connector->getPort())) {
            $portString = "port={$this->connector->getPort()};";
        }

        return "mysql:host={$this->connector->getHostname()};{$portString}dbname={$this->connector->getDatabase()}";
    }

    /**
     * @inheritdoc
     */
    public function connect($options = [])
    {
        // Enable persistent connection if not in debug mode

        /** @noinspection PhpUndefinedClassConstantInspection */

        $this->databaseAttributes = [PDO::ATTR_PERSISTENT         => isset($options['debug']) ? $options['debug'] : false,
                                     PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'];

        return parent::connect($options);
    }
}
