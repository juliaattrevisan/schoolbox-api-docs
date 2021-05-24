<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Database\Transactor\Strategy;

use PDO;

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

    /**
     * Sets the PDO connection.
     *
     * @param  PDO $connection
     * @return IStrategy
     */
    public function setConnection(PDO $connection): self;
}
