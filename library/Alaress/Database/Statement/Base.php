<?php

/**
 * Base statement implementation
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Database\Statement;

use Alaress\Database\Broker\IBroker;
use Alaress\Database\Collector;
use Alaress\Database\Exception;
use Alaress\Database\Iterator\IIterator;
use Alaress\Sentry\Tracing\Database\TracedPdoStatement;
use PDO;
use PDOException;
use PDOStatement;

abstract class Base implements IStatement
{
    /** @var IBroker */
    protected $broker;

    /** @var PDOStatement This is the PDO statement that will do the actual work */
    protected $statement;

    /** @var array $parameters This is an array that contains parameters we intend to bind to the PDO statement */
    protected $parameters = [];

    /**
     * On destruction of statement make sure the cursor is closed. This might cover our rear ends in some
     * situations where we don't close off a query/proc call properly- but this is not a guarantee as it may
     * still be referenced somewhere- so don't rely on it.
     *
     * Close or fully iterate your query result set!
     */
    public function __destruct()
    {
        if ($this->statement instanceof PDOStatement) {
            $this->statement->closeCursor();
        }
    }

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
    public function close()
    {
        return $this->statement->closeCursor();
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

        // If using PDO::FETCH_COLUMN, the row value may be falsy (eg int 0), need to test for explicit FALSE
        while (($row = $this->statement->fetch($fetchStyle)) !== false) {
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
    public function nextRowset()
    {
        $statement = $this->statement;

        try {
            return $statement->nextRowset();
        } catch (PDOException $e) {
            throw new Exception("Error retrieving next rowset: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * The should be completed in the statement class to bind the appropriate values
     */
    abstract protected function bindStatement();

    /**
     * Prepare a statement for execution and bind input parameters
     *
     * @param array|null $values
     * @return bool
     * @throws Exception if preparing execution failed for some reason
     */
    private function prepareExecution($values)
    {
        if (empty($this->statement)) {
            try {
                $this->statement = new TracedPdoStatement(
                    $this->broker->prepare($this->getSql()),
                    $this->broker->getDriver(),
                );
            } catch (PDOException $e) {
                throw new Exception("Error preparing execution: " . $e->getMessage(), 0, $e);
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
     * @inheritdoc
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
            throw new Exception("Error executing statement: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritdoc
     */
    public function getIterator(): IIterator
    {
        try {
            $this->prepareExecution(null);
            return $this->broker->getIterator($this, $this->statement);
        } catch (PDOException $e) {
            throw new Exception("Exception error retrieving iterator: " . $e->getMessage(), 0, $e);
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
                default:
                    return 'PARAM_STR';
            }
        }
    }
}
