<?php

/**
 * Iterator
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 *
 * All praise to the amazing @armatronic from whom I blatantly stole this implementation
 */

namespace Alaress\Database\Iterator;

use Alaress\Database\Broker\IBroker;
use Alaress\Database\Collector;
use Alaress\Database\Exception as DatabaseException;
use Alaress\Database\Exception;
use Alaress\Database\Statement\IStatement;
use PDO;
use PDOException;
use PDOStatement;

class Base implements IIterator
{
    /** @var int $rowIndex Zero-indexed value used for row counting */
    protected $rowIndex = -1;

    /** @var array|bool $currentRow */
    protected $currentRow = false;

    /** @var IStatement */
    protected $statement;

    /** @var PDOStatement */
    protected $pdoStatement;

    /** @var IBroker */
    protected $broker;

    /**
     * Database statement constructor
     *
     * @param IBroker $broker
     * @param IStatement &$statement
     * @param PDOStatement &$pdoStatement
     */
    public function __construct($broker, $statement, $pdoStatement)
    {
        $this->broker = $broker;
        $this->statement = $statement;
        $this->pdoStatement = $pdoStatement;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        try {
            if ($this->broker->getCollector() instanceof Collector) {
                $result = $this->broker->getCollector()->execute($this->statement);
            } else {
                $result = $this->pdoStatement->execute();
            }

            if ($result) {
                $this->next();
            }
        } catch (PDOException $e) {
            throw new Exception($e->getMessage(), 0, $e);
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function current()
    {
        if ($this->rowIndex == -1) {
            throw new DatabaseException('Iterator has not been executed.');
        }

        return $this->currentRow;
    }

    /**
     * @inheritdoc
     */
    public function next()
    {
        ++$this->rowIndex;
        $this->currentRow = $this->pdoStatement->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT);

        /* Call post processor on rowset */
        foreach ($this->broker->getPostProcessors() as $callable) {
            $this->currentRow = call_user_func($callable, $this->currentRow);
        }
    }

    /**
     * @inheritdoc
     */
    public function key()
    {
        return $this->rowIndex;
    }

    /**
     * @inheritdoc
     */
    public function valid()
    {
        return false !== $this->currentRow;
    }

    /**
     * @inheritdoc
     */
    public function rewind()
    {
        // If you want to rewind, you'll need to do it in the connector specific class as it is not universally supported
        // the base implementation will do nothing

        return;
    }

    /**
     * @inheritdoc
     */
    public function count()
    {
        if ($this->statement == null) {
            return 0;
        }

        return $this->statement->rowCount();
    }

    /**
     * @inheritdoc
     */
    public function nextRowset()
    {
        $result = $this->pdoStatement->nextRowset();

        if ($result) {
            $this->rowIndex = -1;
            $this->next();
        }

        return $result;
    }
}
