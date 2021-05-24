<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Database\Transactor;

use Alaress\Database\Transactor\Strategy\IStrategy;
use RuntimeException;
use Throwable;

use function count;

/**
 * Base transactor class, implements the most basic transaction functionality
 * plus hooks that run when the entire transaction is committed.
 */
class Base implements ITransactor
{
    /**
     * A transaction strategy
     *
     * @var IStrategy
     */
    private $strategy;

    /**
     * A list of callbacks to call when the entire transaction is committed.
     *
     * Callbacks are grouped by transaction nesting level:
     * * when the transaction is committed, the current level's callbacks are
     *   merged into the previous level's callbacks
     * * when the transaction is rolled back, the current level's callbacks are
     *   discarded
     *
     * @var callable[][]
     */
    private $onPersistHooks = [];

    /**
     * Constructor
     *
     * @param IStrategy $strategy
     */
    public function __construct(IStrategy $strategy)
    {
        $this->strategy = $strategy;
    }

    /**
     * @inheritdoc
     */
    public function transact(callable $callback): bool
    {
        $this->beginTransaction();
        try {
            $result = $callback();
        } catch (Throwable $e) {
            // Rollback if an exception happened, and rethrow
            $this->rollback();
            throw $e;
        }

        // Otherwise, if false (not null) was returned, rollback
        if ($result === false) {
            $this->rollback();
            return false;
        }
        $this->commit();
        return true;
    }

    /**
     * @inheritdoc
     */
    public function beginTransaction(): bool
    {
        $this->onPersistHooks[] = [];
        return $this->strategy->beginTransaction();
    }

    /**
     * @inheritdoc
     */
    public function inTransaction(): bool
    {
        return $this->strategy->inTransaction();
    }

    /**
     * @inheritdoc
     */
    public function commit(): bool
    {
        if (!$this->inTransaction()) {
            throw $this->buildNotInTransactionException();
        }
        if ($this->strategy->commit()) {
            if (!$this->inTransaction()) {
                // Are all transactions complete? Run hooks FIFO
                // (Clear the stack before running, because otherwise a commit
                //  within the hook will cause each hook within the stack to be
                //  called again)
                $onPersistHooks       = $this->onPersistHooks;
                $this->onPersistHooks = [];
                foreach ($onPersistHooks as $persistHooks) {
                    foreach ($persistHooks as $persistHook) {
                        $persistHook();
                    }
                }
            } else {
                // Not yet complete?
                // Shift this nesting level's hooks onto the next most recent level
                $committedHooks         = array_pop($this->onPersistHooks);
                $currentHooks           = array_pop($this->onPersistHooks);
                $this->onPersistHooks[] = array_merge($currentHooks, $committedHooks);
            }
            return true;
        }
        // Something went wrong
        return false;
    }

    /**
     * @inheritdoc
     */
    public function rollback(): bool
    {
        if (!$this->inTransaction()) {
            throw $this->buildNotInTransactionException();
        }
        if ($this->strategy->rollback()) {
            if (!$this->inTransaction()) {
                // Are all transactions complete?
                // Discard all hooks
                $this->onPersistHooks = [];
            } else {
                // Not yet complete?
                // Discard current level's hooks
                array_pop($this->onPersistHooks);
            }
            return true;
        }
        // Something went wrong
        return false;
    }

    /**
     * Add a callback that is called once (and only if) the outermost nesting
     * level is committed.
     *
     * Note that if the transactor is unexpectedly no longer in a transaction
     * (e.g. if there was an implicit commit) there is no way to tell whether
     * the transaction was committed or rolled back.
     * If this happens, none of these callback methods will be called
     * (and `commit` will raise an exception).
     *
     * @param callable $callback
     * @return ITransactor
     */
    public function onPersist(callable $callback): ITransactor
    {
        $persistStackSize = count($this->onPersistHooks);
        if ($persistStackSize > 0) {
            // put in the transaction Q, in the last possible point
            // Hope that onPersistHooks is in sync with transaction state
            $this->onPersistHooks[$persistStackSize - 1][] = $callback;
        } else {
            // just call immediately
            $callback();
        }
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getStrategy(): IStrategy
    {
        return $this->strategy;
    }

    /**
     * @return RuntimeException
     */
    private function buildNotInTransactionException(): RuntimeException
    {
        return new RuntimeException(
            '`commit` or `rollback` may not be called when not in a transaction. ' .
            'Either this was called without first calling `beginTransaction`, or the database ' .
            'implicitly committed the transaction (possibly due to a schema change).'
        );
    }
}
