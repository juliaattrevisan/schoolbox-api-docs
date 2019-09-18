<?php

/**
 * Base connector class
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Database\Connector;

use Alaress\Database\Broker\IBroker;
use Alaress\Database\Predicate\Builder;
use Alaress\Database\Statement\FunctionCall\IFunctionCall;
use Alaress\Database\Statement\Query\IQuery;
use Alaress\Database\Statement\StoredProcedure\IStoredProcedure;
use Alaress\Database\Transactor\ITransactor;
use Serializable;

class Base implements Serializable, IConnector
{
    /** @var IBroker */
    protected $broker;

    /** @var ITransactor */
    protected $transactor;

    /** @param array */
    protected $options;

    /** @param string */
    protected $dsn;

    /** @var string */
    protected $hostname;

    /** @var string */
    protected $database;

    /** @var string */
    protected $username;

    /** @var string */
    protected $password;

    /** @var string $error Last reported error */
    protected $error;

    /** @var string $error Last reported error code */
    protected $errorCode;

    /**
     * Connector constructor
     *
     * @param IBroker     $broker     Type specific broker
     * @param ITransactor $transactor Transactor appropriate for this broker
     */
    public function __construct(IBroker $broker, ITransactor $transactor)
    {
        $this->broker     = $broker;
        $this->transactor = $transactor;
    }


    /**
     * Connect to the database
     *
     * @param string $hostname
     * @param string $database
     * @param string $username
     * @param string $password
     * @param array $options Database connection options
     * @return bool
     * @throws \Alaress\Database\Exception
     */
    public function connect($hostname, $database, $username, $password, $options = [])
    {
        $this->options = $options;
        $this->username = $username;
        $this->password = $password;
        $this->hostname = $hostname;
        $this->database = $database;

        $this->broker->setConnector($this);
        if ($this->broker->connect($options)) {
            $this->transactor->getStrategy()->setConnection($this->broker->getPdo());
            return true;
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function enableMultipleActiveResultSets()
    {
        $this->broker->enableMultipleActiveResultSets();
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function disableMultipleActiveResultSets()
    {
        $this->broker->disableMultipleActiveResultSets();
        return $this;
    }

    /**
     * @inheritdoc
     *
     * @param string $timezone A valid timezone
     */
    public function setTimezone($timezone)
    {
        $this->broker->setTimezone($timezone);
        return $this;
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Getters for the immutable properties
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function getHostname()
    {
        // Appeasing CI which doesn't seem to like this being done inline with the return statement

        $hostnameArray = explode(":", $this->hostname, 2);
        return $hostnameArray[0];
    }

    /**
     * @inheritdoc
     */
    public function getPort()
    {
        $hostnameArray = explode(":", $this->hostname, 2);

        if (count($hostnameArray) == 2) {
            return $hostnameArray[1];
        } else {
            return null;
        }
    }

    /**
     * @inheritdoc
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @inheritdoc
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @inheritdoc
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @inheritdoc
     */
    public function errorInfo()
    {
        return $this->error;
    }

    /**
     * @inheritdoc
     */
    public function errorCode()
    {
        return $this->errorCode;
    }

    /**
     * @inheritdoc
     */
    public function getCollector()
    {
        return $this->broker->getCollector();
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Serialization functions
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function serialize()
    {
        // We serialize just the settings of the database connection

        return serialize([
            'hostname' => $this->hostname,
            'username' => $this->username,
            'password' => $this->password,
            'database' => $this->database,
            'brokerClass' => get_class($this->broker)
        ]);
    }

    /**
     * @inheritdoc
     */
    public function unserialize($serialized)
    {
        $settings = unserialize($serialized);

        // We load the settings back into the object

        $this->hostname = $settings['hostname'];
        $this->username = $settings['username'];
        $this->password = $settings['password'];
        $this->database = $settings['database'];

        // Create the correct broker class

        $this->broker = new $settings['brokerClass'];
        $this->broker->setConnector($this);
        $this->broker->connect();
    }

    /**
     * @inheritdoc
     */
    public function reconnect($options = null)
    {
        if (is_array($options)) {
            $this->options = $options;
        }

        $this->broker->disconnect();
        return $this->broker->connect($this->options);
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Database statement generators
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function prepareQuery($sql, $parameters = [])
    {
        $statement = $this->broker->prepareQuery($sql);

        if (is_array($parameters)) {
            $statement->bindInputs($parameters);
        }

        return $statement;
    }

    /**
     * @inheritdoc
     */
    public function execute($sql)
    {
        return $this->prepareQuery($sql)->execute();
    }

    /**
     * @inheritdoc
     */
    public function prepareFunctionCall($function, $inputs = null, &$outputs = null, &$return = null)
    {
        /** @var IStoredProcedure $statement */
        $statement = $this->broker->prepareFunctionCall($function);

        if ($inputs != null) {
            $statement->bindInputs($inputs);
        }

        if ($outputs != null) {
            $statement->bindOutputs($outputs);
        }

        if ($return != null) {
            $statement->bindReturnValue($return);
        }

        return $statement;
    }

    /**
     * @inheritdoc
     */
    public function executeFunctionCall($function, $inputs = null, &$outputs = null, &$return = null)
    {
        /** @var IFunctionCall $function */
        $function = $this->prepareFunctionCall($function, $inputs, $outputs, $return);
        $function->execute();
        return $function;
    }
    /**
     * @inheritdoc
     */
    public function prepareStoredProcedure($procedure, $inputs = null, &$outputs = null, &$return = null)
    {
        /** @var IStoredProcedure $statement */
        $statement = $this->broker->prepareStoredProcedure($procedure);

        if ($inputs != null) {
            $statement->bindInputs($inputs);
        }

        if ($outputs != null) {
            $statement->bindOutputs($outputs);
        }

        if ($return != null) {
            $statement->bindReturnValue($return);
        }

        return $statement;
    }

    /**
     * @inheritdoc
     */
    public function executeQuery($sql, $parameters = null)
    {
        /** @var IQuery $statement */
        $statement = $this->prepareQuery($sql, $parameters);
        $statement->execute();
        return $statement;
    }

    /**
     * @inheritdoc
     */
    public function getQueryIterator($sql, $parameters = null)
    {
        /** @var IQuery $statement */
        $statement = $this->prepareQuery($sql, $parameters);
        return $statement->getIterator();
    }

    /**
     * @inheritdoc
     */
    public function executeStoredProcedure($procedure, $inputs = null, &$outputs = null, &$return = null)
    {
        /** @var IStoredProcedure $statement */
        $statement = $this->prepareStoredProcedure($procedure, $inputs, $outputs, $return);
        $statement->execute();
        return $statement;
    }

    /**
     * @inheritdoc
     */
    public function getStoredProcedureIterator($procedure, $inputs = null, &$outputs = null, &$return = null)
    {
        /** @var IStoredProcedure $statement */
        $statement = $this->prepareStoredProcedure($procedure, $inputs, $outputs, $return);
        return $statement->getIterator();
    }

    /**
     * @inheritdoc
     */
    public function lastInsertId($name = null) {
        return $this->broker->lastInsertId($name);
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Post processor callback stuff
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function addPostProcessor($callable)
    {
        $this->broker->addPostProcessor($callable);
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Database transaction functions
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function transact(callable $callback)
    {
        $this->transactor->transact($callback);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function onPersist(callable $callback)
    {
        $this->transactor->onPersist($callback);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function beginTransaction()
    {
        return $this->transactor->beginTransaction();
    }

    /**
     * @inheritdoc
     */
    public function inTransaction()
    {
        return $this->transactor->inTransaction();
    }

    /**
     * @inheritdoc
     */
    public function commit()
    {
        return $this->transactor->commit();
    }

    /**
     * @inheritdoc
     */
    public function rollBack()
    {
        return $this->transactor->rollBack();
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Lazy temporary clutch to build IN portion of queries, should integrate this things into the
    // predicate builder
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * @var int $count
     */
    private $count = 0;

    /**
     * @inheritdoc
     */
    public function prepareInBindings($externalIds, $prefix = 'param')
    {
        if (!is_array($externalIds)) {
            $externalIds = [$externalIds];
        }

        $sql = "";
        $bindings = [];

        foreach ($externalIds as $externalId) {
            if (!empty($sql)) {
                $sql .= ',';
            }

            $sql .= ":{$prefix}{$this->count}";
            $bindings[":{$prefix}{$this->count}"] = $externalId;

            $this->count++;
        }

        return [
            'sql' => $sql,
            'bindings' => $bindings
        ];
    }

    /**
     * @inheritdoc
     */
    public function buildPredicateBuilder($table)
    {
        return new Builder($this->broker, $table);
    }

    /**
     * @inheritdoc
     */
    public function quoteIdentifier($string) {
        return $this->broker->quoteIdentifier($string);
    }

    /**
     * @inheritdoc
     */
    public function escapeForLike($string) {
        return $this->broker->escapeForLike($string);
    }

    /**
     * @inheritdoc
     * @todo This should not live here. This class should NOT implement IQuoter
     */
    public function getEscapedOperand(string $defaultTable, string $columnName): string
    {
        return $this->broker->getEscapedOperand($defaultTable, $columnName);
    }
}
