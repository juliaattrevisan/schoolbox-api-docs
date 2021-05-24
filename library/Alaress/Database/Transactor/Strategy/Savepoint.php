<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Database\Transactor\Strategy;

use PDO;

/**
 * Savepoint strategy for database transaction handling. This strategy
 * uses savepoints to emulate nested transactions for databases which don't
 * support nested transactions.
 */
class Savepoint implements IStrategy
{
    /**
     * A database connection broker
     *
     * @var PDO
     */
    private $connection;

    /**
     * Savepoint stack
     *
     * @var int[]
     */
    private $savepointStack = [];

    /**
     * Savepoint number
     *
     * @var int
     */
    private $savepointNumber = 0;

    /**
     * @param PDO $connection
     * @return self
     */
    public function setConnection(PDO $connection): IStrategy
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function beginTransaction(): bool
    {
        // In a transaction? Don't begin another level
        if (!$this->inTransaction() && !$this->connection->beginTransaction()) {
            return false;
        }

        // create a savepoint
        $newSavepoint = $this->generateSavepointNumber();
        if ($this->createSavepoint($newSavepoint)) {
            $this->savepointStack[] = $newSavepoint;
            return true;
        }

        // Something went wrong
        return false;
    }

    /**
     * @inheritdoc
     */
    public function inTransaction(): bool
    {
        return $this->connection->inTransaction();
    }

    /**
     * @inheritdoc
     */
    public function commit(): bool
    {
        // Not in a transaction? something's wrong
        if (!$this->inTransaction()) {
            return false;
        }

        // Release the last level in the savepoint stack
        $lastSavepoint = array_pop($this->savepointStack);
        if (!$this->releaseSavepoint($lastSavepoint)) {
            return false;
        }

        // No more levels left: commit the whole thing
        if (empty($this->savepointStack)) {
            return $this->connection->commit();
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function rollback(): bool
    {
        // Not in a transaction? something's wrong
        if (!$this->inTransaction()) {
            return false;
        }

        // Rollback and release the last level in the savepoint stack
        $lastSavepoint = array_pop($this->savepointStack);
        if (!$this->rollbackToSavepoint($lastSavepoint) && !$this->releaseSavepoint($lastSavepoint)) {
            return false;
        }

        // No more levels left: rollback the whole thing
        if (empty($this->savepointStack)) {
            return $this->connection->rollBack();
        }

        return true;
    }

    /**
     * Generate an auto-incrementing savepoint number.
     *
     * @return int
     */
    private function generateSavepointNumber(): int
    {
        return $this->savepointNumber++;
    }

    /**
     * Creates a savepoint.
     *
     * @param int $number
     * @return bool
     */
    private function createSavepoint(int $number): bool
    {
        // The DB steadfastly refuses to allow me to use bound params in
        // savepoint queries.
        // Inject instead -_- it will be safe as we directly control the input
        $this->connection->exec(sprintf('SAVEPOINT point%d', $number));
        return true;
    }

    /**
     * Rolls back to a savepoint.
     *
     * @param int $number
     * @return bool
     */
    private function rollbackToSavepoint(int $number): bool
    {
        $this->connection->exec(sprintf('ROLLBACK TO SAVEPOINT point%d', $number));
        return true;
    }

    /**
     * Releases a savepoint once it is no longer required.
     *
     * @param int $number
     * @return bool
     */
    private function releaseSavepoint(int $number): bool
    {
        $this->connection->exec(sprintf('RELEASE SAVEPOINT point%d', $number));
        return true;
    }
}
