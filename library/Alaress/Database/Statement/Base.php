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
use PDO;
use PDOException;
use PDOStatement;

abstract Class Base implements IStatement
{
    /** @var IBroker */
    protected $broker;

    /** @var PDOStatement This is the PDO statement that will do the actual work */
    protected $statement;

    /** @var string This is the SQL string the statement generated */
    protected $sql;

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
    public function fetch()
    {
        $result = $this->statement->fetch(PDO::FETCH_ASSOC);

        foreach ($this->broker->getPostProcessors() as $callable) {
            $result = call_user_func($callable, $result);
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function fetchAll()
    {
        $result = $this->statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($this->broker->getPostProcessors() as $callable) {
            $result = call_user_func($callable, $result);
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
        $results = $this->statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($this->broker->getPostProcessors() as $callable) {
            $results = call_user_func($callable, $results);
        }

        if ($index == null) {

            // This returned a boring old ordered list

            return array_map(
                function ($row) use ($column) {
                    return $row[$column];
                },
                $results
            );

        } else {

            // This returns using the specified column as the array index

            $returnArray = [];

            foreach ($results as $row) {
                $returnArray[$row[$index]] = $row[$column];
            }

            return $returnArray;
        }
    }

    /**
     * @inheritdoc
     */
    public function fetchRowArray($index)
    {
        $results = $this->statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($this->broker->getPostProcessors() as $callable) {
            $results = call_user_func($callable, $results);
        }

        // This returns using the specified column as the array index

        $returnArray = [];

        foreach ($results as $row) {
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
     * @inheritdoc
     */
    public function getSql()
    {
        return $this->sql;
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
     * This function does nothing in the base statement. The Query/StoredProcedure overrides should implement parameter
     * binding appropriately.
     */
    protected function bindStatement()
    {
    }

    /**
     * @inheritdoc
     */
    public function execute($values = null)
    {
        try {
            if (empty($this->statement)) {
                try {
                    $this->statement = $this->broker->prepare($this->sql, [PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL]);
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
            // Make sure the statement has been prepared

            if (empty($this->statement)) {
                try {
                    $this->statement = $this->broker->prepare($this->sql, [PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL]);
                } catch (PDOException $e) {
                    $this->handleException($e);
                    return false;
                }
            }

            // Bind any last minute input parameters from the values array passed to this function

            if (is_array($values)) {
                foreach ($values as $key => $value) {
                    $this->statement->bindValue($key, $value);
                }
            }

            // Bind parameters to the statement

            $this->bindStatement();

            // Grab iterator

            $iterator = $this->broker->getIterator($this, $this->statement);

            // Execute the statement

            if (!$iterator->execute()) {

                // Query execution failed

                return false;
            }
            // If we got to this point we got a valid iterator which had rows

            return $iterator;

        } catch (PDOException $e) {
            return $this->handleException($e);
        }
    }
}