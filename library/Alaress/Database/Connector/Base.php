<?php

/**
 * Base connector class
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Connector;

use Alaress\Database\Broker\IBroker;
use Alaress\Database\Statement\StoredProcedure\IStoredProcedure;
use Alaress\Database\Statement\Query\IQuery;

abstract class Base implements IConnector
{
    // ----------------------------------------------------------------------------------------------------------------
    // This should be overridden in the child classes with the database engine specific broker
    // and only exists here to keep storms type hinting happy
    // ----------------------------------------------------------------------------------------------------------------

    /** @var IBroker */
    protected $broker;

    // ----------------------------------------------------------------------------------------------------------------
    // Common properties
    // ----------------------------------------------------------------------------------------------------------------

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

        $serializedInstance = [
            'hostname' => $this->hostname,
            'username' => $this->username,
            'password' => $this->password,
            'database' => $this->database
        ];

        return json_encode($serializedInstance);
    }

    /**
     * @inheritdoc
     */
    protected function unserialize($serialized)
    {
        $serialized = json_decode($serialized);

        // We load the settings back into the object

        $this->hostname = $serialized->hostname;
        $this->username = $serialized->username;
        $this->password = $serialized->password;
        $this->database = $serialized->database;
    }

    /**
     * @inheritdoc
     */
    public function reconnect($options = null)
    {
        if (is_array($options)) {
            $this->options = $options;
        }

        unset($this->broker);
        return $this->broker->connect($this->options);
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Database statement generators
    // ----------------------------------------------------------------------------------------------------------------

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
    public function executeQueryIterator($sql, $parameters = null)
    {
        /** @var IQuery $statement */
        $statement = $this->prepareQuery($sql, $parameters);
        return $statement->executeIterator();
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
    public function executeStoredProcedureIterator($procedure, $inputs = null, &$outputs = null, &$return = null)
    {
        /** @var IStoredProcedure $statement */
        $statement = $this->prepareStoredProcedure($procedure, $inputs, $outputs, $return);
        return $statement->executeIterator();
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
    public function beginTransaction()
    {
        return $this->broker->beginTransaction();
    }

    /**
     * @inheritdoc
     */
    public function inTransaction()
    {
        return $this->broker->inTransaction();
    }

    /**
     * @inheritdoc
     */
    public function commit()
    {
        return $this->broker->commit();
    }

    /**
     * @inheritdoc
     */
    public function rollBack()
    {
        return $this->broker->rollBack();
    }
}