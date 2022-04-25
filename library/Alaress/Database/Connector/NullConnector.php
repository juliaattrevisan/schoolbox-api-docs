<?php

/**
 *
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Connector;

use Alaress\Database\Exception;
use Alaress\Database\Iterator\IIterator;
use Alaress\Database\Quoter\IQuoter;
use Alaress\Database\Statement\IStatement;
use Alaress\Database\Statement\StoredProcedure\IStoredProcedure;

class NullConnector implements IConnector
{

    /**
     * @inheritDoc
     */
    public function connect($hostname, $database, $username, $password, $options = [])
    {
        return false;
    }


    /**
     * @inheritDoc
     */
    public function reconnect($options = null)
    {
        return false;
    }


    /**
     * @inheritDoc
     */
    public function enableMultipleActiveResultSets()
    {
        return $this;
    }


    /**
     * @inheritDoc
     */
    public function disableMultipleActiveResultSets()
    {
        return $this;
    }


    /**
     * @inheritDoc
     */
    public function setTimezone($timezone)
    {
        return $this;
    }


    /**
     * @inheritDoc
     */
    public function execute($sql)
    {
        return false;
    }


    /**
     * @inheritDoc
     */
    public function executeDirectly(string $sql): bool
    {
        return false;
    }


    /**
     * @inheritDoc
     */
    public function prepareQuery($query, $parameters = [])
    {
        throw new Exception();
    }


    /**
     * @inheritDoc
     */
    public function executeQuery($sql, $parameters = null)
    {
        throw new Exception();
    }


    /**
     * @inheritDoc
     */
    public function getQueryIterator($sql, $parameters = null)
    {
        throw new Exception();
    }


    /**
     * @inheritDoc
     */
    public function prepareFunctionCall($function, $inputs = null, &$outputs = null, &$return = null)
    {
        throw new Exception();
    }


    /**
     * @inheritDoc
     */
    public function executeFunctionCall($function, $inputs = null, &$outputs = null, &$return = null)
    {
        throw new Exception();
    }


    /**
     * @inheritDoc
     */
    public function prepareStoredProcedure($procedure, $inputs = null, &$outputs = null, &$return = null)
    {
        throw new Exception();
    }


    /**
     * @inheritDoc
     */
    public function executeStoredProcedure($procedure, $inputs = null, &$outputs = null, &$return = null)
    {
        throw new Exception();
    }


    /**
     * @inheritDoc
     */
    public function getStoredProcedureIterator($procedure, $inputs = null, &$outputs = null, &$return = null)
    {
        throw new Exception();
    }


    /**
     * @inheritDoc
     */
    public function lastInsertId($name = null)
    {
        return false;
    }


    /**
     * @inheritDoc
     */
    public function transact(callable $callback)
    {
        return $this;
    }


    /**
     * @inheritDoc
     */
    public function onPersist(callable $callback)
    {
        return $this;
    }


    /**
     * @inheritDoc
     */
    public function beginTransaction()
    {
        return false;
    }


    /**
     * @inheritDoc
     */
    public function inTransaction()
    {
        return false;
    }


    /**
     * @inheritDoc
     */
    public function commit()
    {
        return false;
    }


    /**
     * @inheritDoc
     */
    public function rollBack()
    {
        return false;
    }


    /**
     * @inheritDoc
     */
    public function errorInfo()
    {
        return '';
    }


    /**
     * @inheritDoc
     */
    public function errorCode()
    {
        return '';
    }


    /**
     * @inheritDoc
     */
    public function getHostname()
    {
        return '';
    }


    /**
     * @inheritDoc
     */
    public function getPort()
    {
        return '';
    }


    /**
     * @inheritDoc
     */
    public function getUsername()
    {
        return '';
    }


    /**
     * @inheritDoc
     */
    public function getPassword()
    {
        return '';
    }


    /**
     * @inheritDoc
     */
    public function getDatabase()
    {
        return '';
    }


    /**
     * @inheritDoc
     */
    public function getCollector()
    {
        return null;
    }


    /**
     * @inheritDoc
     */
    public function addPostProcessor($callable)
    {
    }


    /**
     * @inheritDoc
     */
    public function prepareInBindings($externalIds, $prefix = 'param')
    {
        return [];
    }


    /**
     * @inheritDoc
     */
    public function buildPredicateBuilder($table)
    {
        throw new Exception();
    }


    /**
     * @inheritDoc
     */
    public function getQuoter(): IQuoter
    {
        throw new Exception();
    }


    /**
     * @inheritDoc
     */
    public function getRow(string $sql, array $parameters = null)
    {
        return false;
    }


    /**
     * @inheritDoc
     */
    public function getOne(string $sql, array $parameters = null)
    {
        return '';
    }


    /**
     * @inheritDoc
     */
    public function getAll(string $sql, array $parameters = null): array
    {
        return [];
    }


    /**
     * @inheritDoc
     */
    public function getIterator(string $sql, array $parameters = null): IIterator
    {
        throw new Exception();
    }


    /**
     * @inheritDoc
     */
    public function query(string $sql, array $parameters = null): IStatement
    {
        throw new Exception();
    }


    /**
     * @inheritDoc
     */
    public function prepareProcedure(string $name): IStoredProcedure
    {
        throw new Exception();
    }


    /**
     * @inheritDoc
     */
    public function legacyQuote($value): string
    {
        return $value;
    }


    /**
     * @inheritDoc
     */
    public function trimAll(array $rowset): array
    {
        return $rowset;
    }


    /**
     * @inheritDoc
     */
    public function trimRow(array $row): array
    {
        return $row;
    }


    /**
     * @inheritDoc
     */
    public function trimField($fieldValue)
    {
        return $fieldValue;
    }
}
