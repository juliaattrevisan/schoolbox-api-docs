<?php

/**
 * Base statement implementation
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Statement;

use Alaress\Database\Broker\IBroker;
use Alaress\Database\Collector;
use Alaress\Database\Exception;
use DebugBar\DataCollector\PDO\PDOCollector;
use PDO;
use PDOException;
use PDOStatement;

abstract Class Base implements IStatement
{
    /** @var IBroker */
    protected $broker;

    /** @var PDOStatement This is the PDO statement that will do the actual work */
    protected $statement;

    /** @var string $errorInfo If the last statement execution returned an error- this is its message */
    protected $errorInfo;

    /** @var string $errorCode If the last statement execution returned an error- this is its code */
    protected $errorCode;

    /** @var array $parameters This is an array that contains parameters we intend to bind to the PDO statement */
    protected $parameters = [];

    /**
     * @inheritdoc
     */
    public function rowCount()
    {
        return $this->statement->rowCount();
    }

    /**
     * @inheritdoc
     */
    public function fetch($fetchStyle = PDO::FETCH_ASSOC)
    {
        $result = $this->statement->fetch($fetchStyle);

        foreach ($this->broker->getPostProcessors() as $callable) {
            $result = call_user_func($callable, $result);
        }

        return $result;
    }

    /**
     * Make sure you really want to use this- it has the potential to be a memory hog. If you're iterating results, use an iterator!
     *
     * @inheritdoc
     */
    public function fetchAll($fetchStyle = PDO::FETCH_ASSOC)
    {
        $result = [];

        while ($row = $this->statement->fetch($fetchStyle)) {
            foreach ($this->broker->getPostProcessors() as $callable) {
                 $row = call_user_func($callable, $row);
            }
            $result[] = $row;
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function fetchColumn($column = 0)
    {
        $result = $this->statement->fetchColumn($column);

        foreach ($this->broker->getPostProcessors() as $callable) {
            $result = call_user_func($callable, $result);
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function fetchColumnArray($column, $index = null)
    {
        $returnArray = [];

        while ($row = $this->statement->fetch(PDO::FETCH_ASSOC)) {
            foreach ($this->broker->getPostProcessors() as $callable) {
                $row = call_user_func($callable, $row);
            }

            if ($index == null) {
                $returnArray[] = $row[$column];
            } else {
                $returnArray[$row[$index]] = $row[$column];
            }
        }

        return $returnArray;
    }

    /**
     * @inheritdoc
     */
    public function fetchRowArray($index)
    {
        $returnArray = [];

        while ($row = $this->statement->fetch(PDO::FETCH_ASSOC)) {
            foreach ($this->broker->getPostProcessors() as $callable) {
                $row = call_user_func($callable, $row);
            }

            $returnArray[$row[$index]] = $row;
        }

        return $returnArray;
    }

    /**
     * @inheritdoc
     */
    public function errorInfo()
    {
        return $this->errorInfo;
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
    public function nextRowset()
    {
        /** @var PDOStatement $statement */
        $statement = $this->statement;

        try {
            return $statement->nextRowset();
        } catch (PDOException $e) {
            $this->handleException($e);
            return null;
        }
    }

    /**
     * If we get a PDO exception running a query we don't want to terminate application
     * execution, instead we store the error message/code on the statement and returns false
     *
     * @param PDOException $e
     * @return bool
     */
    protected function handleException($e)
    {
        $this->errorInfo = $e->getMessage();
        $this->errorCode = $e->getCode();
        return false;
    }

    /**
     * The should be completed in the statement class to bind the appropriate values
     */
    protected abstract function bindStatement();

    /**
     * Prepare a statement for execution and bind input parameters
     *
     * @param $values
     * @return bool
     */
    private function prepareExecution($values)
    {
        if (empty($this->statement)) {
            try {
                $this->statement = $this->broker->prepare($this->getSql(), [PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL]);
            } catch (PDOException $e) {
                $this->handleException($e);
                return false;
            }
        }

        if (is_array($values)) {
            foreach ($values as $key => $value) {
                $this->statement->bindValue($key, $value);
            }
        }

        // Bind parameters to the statement

        $this->bindStatement();
        return true;
    }

    /**
     * This returns the underlying PDO statement. This is used by the debugging collector.
     *
     * @return PDOStatement
     */
    public function getPDOStatement()
    {
        return $this->statement;
    }

    /**
     * @inheritdoc
     */
    public function execute($values = null)
    {
        try {
            $this->prepareExecution($values);

            if ($this->broker->getCollector() instanceof Collector) {
                return $this->broker->getCollector()->execute($this);
            } else {
                return $this->statement->execute();
            }
        } catch (PDOException $e) {
            return $this->handleException($e);
        }
    }

    /**
     * @inheritdoc
     */
    public function executeIterator($values = null)
    {
        try {
            $this->prepareExecution($values);

            // Grab iterator
            $iterator = $this->broker->getIterator($this, $this->statement);

            // Execute the statement
            if (!$iterator->execute()) {

                // Query execution failed
                throw new Exception("Error executing iterator: " . $iterator->errorInfo());
            }

            // If we got to this point we got a valid iterator which had rows
            return $iterator;

        } catch (PDOException $e) {
            throw new Exception("Exception error executing iterator: " . $e->getMessage());
        }
    }

    /**
     * @inheritdoc
     */
    public static function getDataTypeName($value, $type = null)
    {
        if (empty($type)) {

            // Type is being automatically derived- display the name of the derived type

            switch (true) {
                case is_int($value):
                    return 'PARAM_INT';
                case is_bool($value):
                    return 'PARAM_BOOL';
                case is_null($value):
                    return 'PARAM_NULL';
                default:
                    return 'PARAM_STR';
            }
        } else {

            // Type was overidden- display the name of the override type

            switch ($type) {
                case PDO::PARAM_INT:
                    return 'PARAM_INT';
                case PDO::PARAM_BOOL:
                    return 'PARAM_BOOL';
                case PDO::PARAM_NULL:
                    return 'PARAM_NULL';
                case PDO::PARAM_STR:
                    return 'PARAM_STR';
            }
        }

        return $type;
    }
}