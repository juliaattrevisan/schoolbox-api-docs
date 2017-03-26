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

    /** @var string $errorInfo If the last statement execution returned an error- this is its message */
    protected $errorInfo;

    /** @var string $errorCode If the last statement execution returned an error- this is its code */
    protected $errorCode;

    /**
     * @inheritdoc
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
            return $this->handleException($e);
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
     * @iterator
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
    public function nextRowset()
    {
        $result = $this->pdoStatement->nextRowset();

        if ($result) {
            $this->rowIndex = -1;
            $this->next();
        }

        return $result;
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
}