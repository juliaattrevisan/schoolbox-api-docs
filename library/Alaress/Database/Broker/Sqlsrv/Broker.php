<?php

/**
 * MSSQL Database connection broker
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Database\Broker\Sqlsrv;

use Alaress\Database\Broker\Base;
use Alaress\Database\Factory;
use Alaress\Database\Iterator\Sqlsrv\Iterator;
use Alaress\Database\Statement\Query\Sqlsrv\Query;
use Alaress\Database\Statement\FunctionCall\Sqlsrv\FunctionCall;
use Alaress\Database\Statement\StoredProcedure\Sqlsrv\StoredProcedure;
use PDO;

use function in_array;

class Broker extends Base
{
    // This extends the parent connect function with MSSQL specific connection attributes and
    // post connection queries that need to be executed

    /**
     * @inheritdoc
     */
    public static function isAvailable(): bool
    {
        return in_array(Factory::PDO_DRIVER_SQLSRV, PDO::getAvailableDrivers(), true);
    }

    /**
     * @inheritdoc
     */
    public function connect($options = [])
    {
        // Set MSSQL specific attributes:
        // * UTF-8 Encode Query Responses
        // * fetch numeric types as numbers, instead of as strings
        // * Login timeout: 2s
        // * Query timeout: 30s
        $this->connectionOptions = [
            'LoginTimeout' => self::LOGIN_TIMEOUT,
        ];

        $this->databaseAttributes = [
            PDO::SQLSRV_ATTR_ENCODING             => PDO::SQLSRV_ENCODING_UTF8,
            PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE => true,
            PDO::SQLSRV_ATTR_QUERY_TIMEOUT        => self::QUERY_TIMEOUT,
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
            $portString = ",{$this->connector->getPort()}";
        }

        if (isset($this->options['MultipleActiveResultSets'])) {
            $multipleActiveResultsSets = "1";
        } else {
            $multipleActiveResultsSets = "0";
        }

        return
            "sqlsrv:Server={$this->connector->getHostname()}{$portString};" .
            "Database={$this->connector->getDatabase()};" .
            "TrustServerCertificate=True;" .
            "Encrypt={$this->options['Encrypt']};" .                        // Encrypt the connection
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

    /**
     * @inheritdoc
     */
    public function lastInsertId($name = null)
    {
        return $this->connector->executeQuery("SELECT SCOPE_IDENTITY()")->fetchColumn();
    }

    /**
     * Override the base implementation to escape `}` characters in the
     * password.
     *
     * @see https://docs.microsoft.com/en-us/sql/connect/php/how-to-connect-using-sql-server-authentication?view=sql-server-ver15
     *
     * @param array $connectOptions
     * @return PDO
     */
    protected function buildPdoConnection(array $connectOptions): PDO
    {
        return new PDO(
            $this->getDsn(),
            $this->connector->getUsername(),
            str_replace('}', '}}', $this->connector->getPassword()),
            $connectOptions,
        );
    }
}
