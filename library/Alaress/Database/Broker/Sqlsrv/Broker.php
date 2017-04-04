<?php

/**
 * MSSQL Database connection broker
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Broker\Sqlsrv;

use Alaress\Database\Broker\Base;
use Alaress\Database\Broker\IBroker;
use Alaress\Database\Iterator\Sqlsrv\Iterator;
use Alaress\Database\Statement\FunctionCall\Sqlsrv\FunctionCall;
use Alaress\Database\Statement\StoredProcedure\Sqlsrv\StoredProcedure;
use PDO;

class Broker extends Base implements IBroker
{
    // This extends the parent connect function with MSSQL specific connection attributes and
    // post connection queries that need to be executed

    /**
     * @inheritdoc
     */
    public function connect($options = [])
    {
        // Set MSSQL specific attributes

        /** @noinspection PhpUndefinedClassConstantInspection */

        $this->databaseAttributes = [PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8];

        // Set MSSQL specific post-connect queries

        $this->postConnectQueries = [
            'SET ANSI_NULL_DFLT_ON ON',
            'SET ANSI_NULLS ON',
            'SET CONCAT_NULL_YIELDS_NULL ON',
            'SET ANSI_WARNINGS ON',
            'SET NOCOUNT ON'
        ];

        return parent::connect($options);
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
            $portString = ",{$this->connector->getPort()};";
        }

        return "sqlsrv:Server={$this->connector->getHostname()};{$portString}Database={$this->connector->getDatabase()};MultipleActiveResultSets=0;";
    }
}