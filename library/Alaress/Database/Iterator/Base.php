<?php

/**
 * Iterator
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 *
 * All praise to the amazing @armatronic from whom I blatantly stole this implementation
 */

namespace Alaress\Database\Iterator;

use Alaress\Database\Broker\IBroker;
use Alaress\Database\Collector;
use Alaress\Database\Exception as DatabaseException;
use Alaress\Database\Exception;
use Alaress\Database\Statement\IStatement;
use Alaress\Sentry\Tracing\Tracer;
use PDO;
use PDOException;
use PDOStatement;
use Sentry\Tracing\SpanStatus;

class Base implements IIterator
{
    /** @var int $rowIndex Zero-indexed value used for row counting */
    protected $rowIndex = -1;

    /** @var int $rowsetIndex Zero-indexed value for rowset counting */
    protected $rowsetIndex = 0;

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
     * @param IStatement $statement
     * @param PDOStatement $pdoStatement
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
        Tracer::getDefaultTrace()->startSpan(
            $this->pdoStatement->queryString,
            'db.sql.query',
            'auto.db',
            data: ['db.system' => $this->broker->getDriver(),],
        );
        try {
            if ($this->broker->getCollector() instanceof Collector) {
                $result = $this->broker->getCollector()->execute($this->statement);
            } else {
                $result = $this->pdoStatement->execute();
            }

            if ($result) {
                $this->rowsetIndex = 0;
                $this->next();
            }
        } catch (PDOException $e) {
            Tracer::getDefaultTrace()->finishSpan(status: SpanStatus::internalError());
            throw new Exception($e->getMessage(), 0, $e);
        }

        Tracer::getDefaultTrace()->finishSpan();
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function current(): array|bool
    {
        if ($this->rowIndex == -1) {
            throw new DatabaseException('Iterator has not been executed.');
        }

        return $this->currentRow;
    }

    /**
     * @inheritdoc
     */
    public function next(): void
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
    public function key(): int
    {
        return $this->rowIndex;
    }

    /**
     * @inheritdoc
     */
    public function valid(): bool
    {
        return false !== $this->currentRow;
    }

    /**
     * @inheritdoc
     */
    public function rewind(): void
    {
        // If you want to rewind, you'll need to do it in the connector specific class as it is not universally supported
        // the base implementation will do nothing
    }

    /**
     * @inheritdoc
     */
    public function count(): int
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
            $this->rowsetIndex++;
            $this->rowIndex = -1;
            $this->next();
        }

        return $result;
    }

    /**
     * Re-execute, and skip to whichever rowset the current iterator is on.
     *
     * @return bool Was the skip successful?
     * @throws Exception if execute fails
     */
    protected function executeToCurrentRowset(): bool
    {
        $currentRowsetIndex = $this->rowsetIndex;
        $this->execute();
        return $this->skipToRowset($currentRowsetIndex);
    }

    /**
     * Skip to the given rowset number.
     *
     * @param int $index
     * @return bool Was the skip successful?
     */
    protected function skipToRowset($index): bool
    {
        while ($this->rowsetIndex < $index) {
            if (!$this->nextRowset()) {
                return false;
            }
        }
        return true;
    }
}
