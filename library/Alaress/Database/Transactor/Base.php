<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Database\Transactor;

use Alaress\Database\Broker\IBroker;

/**
 * Base transactor class, implements the most basic transaction functionality
 * plus hooks that run when the entire transaction is committed.
 */
class Base
{
    /**
     * A database connection
     *
     * @var IBroker
     */
    private $broker;

    /**
     * A list of callbacks to call when the entire transaction is committed.
     *
     * @var callable[][]
     */
    private $onPersistHooks = [];

    /**
     * Constructor
     *
     * @param IBroker $broker
     */
    public function __construct(IBroker $broker)
    {
        $this->broker = $broker;
    }

    /**
     * Begin transaction
     *
     * @return bool Did it begin?
     */
    public function beginTransaction(): bool
    {
        $this->onPersistHooks[] = [];
        return $this->broker->beginTransaction();
    }

    /**
     * Is the broker in a transaction?
     *
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->broker->inTransaction();
    }

    /**
     * Commit transaction
     *
     * @return bool Did it commit?
     */
    public function commit(): bool
    {
        if ($this->broker->commit()) {
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
     * Rollback current transaction
     *
     * @return bool Did it roll back?
     */
    public function rollback(): bool
    {
        if ($this->broker->rollBack()) {
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
}
