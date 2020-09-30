<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Database\Broker\Dblib;

use Alaress\Database\Broker\Base;
use Alaress\Database\Iterator\Dblib\Iterator;
use Alaress\Database\Quoter\Dblib\Quoter as DblibQuoter;
use Alaress\Database\Statement\FunctionCall\Dblib\FunctionCall;
use Alaress\Database\Statement\Query\Dblib\Query;
use Alaress\Database\Statement\StoredProcedure\Dblib\StoredProcedure;
use PDO;

/**
 * FreeTDS/MSSQL Database connection broker
 *
 * @deprecated use the SQLSrv driver instead
 */
class Broker extends Base
{
    // This extends the parent connect function with MSSQL specific connection attributes and
    // post connection queries that need to be executed

    /**
     * @inheritdoc
     */
    public static function isAvailable()
    {
        return in_array('dblib', PDO::getAvailableDrivers(), true);
    }

    /**
     * @inheritdoc
     */
    protected function buildQuoter()
    {
        return new DblibQuoter();
    }

    /**
     * @inheritdoc
     */
    public function connect($options = [])
    {
        // Set MSSQL specific attributes

        /** @noinspection PhpUndefinedClassConstantInspection */
        $this->databaseAttributes = [];

        // Set MSSQL specific post-connect queries

        $this->postConnectQueries = [
            'SET ANSI_NULL_DFLT_ON ON',
            'SET ANSI_NULLS ON',
            'SET CONCAT_NULL_YIELDS_NULL ON',
            'SET ANSI_WARNINGS OFF',
            'SET ARITHABORT ON',
            'SET NOCOUNT ON'
        ];

        return parent::connect($options);
    }

    /**
     * Attempt to set the connection's timezone.
     *
     * @param string $timezone A valid timezone.
     * @return bool
     */
    public function setTimezone($timezone)
    {
        return true;
    }


    // These return MSSQL specific statement objects

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
    public function prepareQuery($query)
    {
        return new Query($this, $query);
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
    public function getDsn()
    {
        $portString = '';

        if (!empty($this->connector->getPort())) {
            $portString = ":{$this->connector->getPort()}";
        }

        return "dblib:version=7.0;charset=UTF-8;host={$this->connector->getHostname()}{$portString};dbname={$this->connector->getDatabase()};";
    }

    /**
      * @inheritdoc
      */
    public function lastInsertId($name = null)
    {
        return $this->connector->executeQuery("SELECT SCOPE_IDENTITY()")->fetchColumn();
    }
}
