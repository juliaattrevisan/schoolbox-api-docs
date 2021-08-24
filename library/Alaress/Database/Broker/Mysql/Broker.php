<?php

/**
 * MySQL Database connection broker
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Database\Broker\Mysql;

use Alaress\Database\Broker\Base;
use Alaress\Database\Iterator\Mysql\Iterator;
use Alaress\Database\Statement\FunctionCall\Mysql\FunctionCall;
use Alaress\Database\Statement\StoredProcedure\Mysql\StoredProcedure;
use PDO;

class Broker extends Base
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
    public function getDsn()
    {
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
        // When connecting: use the login timeout
        $this->connectionOptions = [
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
            PDO::ATTR_PERSISTENT         => false,
            PDO::ATTR_TIMEOUT            => self::LOGIN_TIMEOUT,
        ];

        // After connecting: use the query timeout
        $this->databaseAttributes = [
            PDO::ATTR_TIMEOUT => self::QUERY_TIMEOUT,
        ];

        return parent::connect($options);
    }

    /**
     * Attempt to set the connection's timezone.
     *
     * @param string $timezone A valid timezone.
     * @return bool
     * @throws PDOException if the timezone can't be set
     */
    public function setTimezone($timezone)
    {
        return $this->pdo
            ->prepare("SET time_zone = ?")
            ->execute([$timezone])
            ;
    }
}
