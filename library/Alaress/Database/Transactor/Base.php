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
        return $this->broker->commit();
    }

    /**
     * Rollback current transaction
     *
     * @return bool Did it roll back?
     */
    public function rollback(): bool
    {
        return $this->broker->rollBack();
    }
}
