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
use Alaress\Database\Iterator\Sqlsrv\Iterator;
use Alaress\Database\Quoter\Sqlsrv\Quoter as SqlsrvQuoter;
use Alaress\Database\Statement\Query\Sqlsrv\Query;
use Alaress\Database\Statement\FunctionCall\Sqlsrv\FunctionCall;
use Alaress\Database\Statement\StoredProcedure\Sqlsrv\StoredProcedure;
use PDO;

class Broker extends Base
{
    // This extends the parent connect function with MSSQL specific connection attributes and
    // post connection queries that need to be executed

    /**
     * @inheritdoc
     */
    public static function isAvailable()
    {
        return in_array('sqlsrv', PDO::getAvailableDrivers(), true);
    }

    /**
     * @inheritdoc
     */
    protected function buildQuoter()
    {
        return new SqlsrvQuoter();
    }

    /**
     * @inheritdoc
     */
    public function connect($options = [])
    {
        // Set MSSQL specific attributes

        /** @noinspection PhpUndefinedClassConstantInspection */

        $this->databaseAttributes = [
            // UTF-8 Encode Query Responses
            PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8,
        ];

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
     * @inheritdoc
     */
    public function enableMultipleActiveResultSets()
    {
        // Enabling MARS required disconnect/reconnecting from the database.

        $this->connector->reconnect(['MultipleActiveResultSets' => true]);
    }

    /**
     * @inheritdoc
     */
    public function disableMultipleActiveResultSets()
    {
        // Enabling MARS required disconnect/reconnecting from the database.

        $this->connector->reconnect(['MultipleActiveResultSets' => false]);
    }

    // These return MSSQL specific statement objects

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

        if (isset($this->options['MultipleActiveResultSets'])) {
            $multipleActiveResultsSets = "1";
        } else {
            $multipleActiveResultsSets = "0";
        }

        return
            "sqlsrv:Server={$this->connector->getHostname()};" .
            $portString .
            "Database={$this->connector->getDatabase()};" .
            "LoginTimeout=" . self::LOGIN_TIMEOUT . ";" .                // Give the connector 5 seconds to login- so we can default back to Offline connector
            "MultipleActiveResultSets={$multipleActiveResultsSets};";    // Disable Multiple Active Result Sets (aka. MARS) for performance reasons
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
}
