<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Database\Transactor;

use Alaress\Database\Transactor\Strategy\IStrategy;

/**
 * Base transactor class, implements the most basic transaction functionality
 * plus hooks that run when the entire transaction is committed.
 */
class Base implements ITransactor
{
    /**
     * A transactor
     *
     * @var IStrategy
     */
    private $transactor;

    /**
     * A list of callbacks to call when the entire transaction is committed.
     *
     * @var callable[][]
     */
    private $onPersistHooks = [];

    /**
     * Constructor
     *
     * @param IStrategy $transactor
     */
    public function __construct(IStrategy $transactor)
    {
        $this->transactor = $transactor;
    }

    /**
     * @inheritdoc
     */
    public function beginTransaction(): bool
    {
        $this->onPersistHooks[] = [];
        return $this->transactor->beginTransaction();
    }

    /**
     * @inheritdoc
     */
    public function inTransaction(): bool
    {
        return $this->transactor->inTransaction();
    }

    /**
     * @inheritdoc
     */
    public function commit(): bool
    {
        if ($this->transactor->commit()) {
            if ($this->inTransaction()) {
                // Are all transactions complete?
                // Run hooks FIFO
                foreach ($this->onPersistHooks as $persistHooks) {
                    foreach ($persistHooks as $persistHook) {
                        $persistHook();
                    }
                }
                $this->onPersistHooks = [];
            } else {
                // Not yet complete?
                // Shift this nesting level's hooks onto the next most recent level
                $committedHooks = array_pop($this->onPersistHooks);
                $currentHooks   = array_pop($this->onPersistHooks);
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
        if ($this->transactor->rollback()) {
            if ($this->inTransaction()) {
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
     * @inheritdoc
     */
    public function onPersist(callable $callback): ITransactor
    {
        $persistStackSize = \count($this->onPersistHooks);
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
}
