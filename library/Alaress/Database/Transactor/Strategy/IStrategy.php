<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Database\Transactor\Strategy;

/**
 * Interface for database transaction handling strategies.
 */
interface IStrategy
{
    /**
     * Begin transaction
     *
     * @return bool Did it begin?
     */
    public function beginTransaction(): bool;

    /**
     * Is the database connection in a transaction?
     *
     * @return bool
     */
    public function inTransaction(): bool;

    /**
     * Commit transaction
     *
     * @return bool Did it commit?
     */
    public function commit(): bool;

    /**
     * Rollback current transaction
     *
     * @return bool Did it roll back?
     */
    public function rollback(): bool;
}
