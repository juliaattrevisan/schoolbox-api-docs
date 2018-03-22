<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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
     * @var string[]
     */
    private $savepointStack = [];

    /**
     * Savepoint number
     *
     * @var int
     */
    private $savepointNumber = 0;

    /**
     * Constructor
     *
     * @param PDO $connection
     */
    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
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
        $newSavepoint = $this->generateSavepointName();
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
     * Generate an auto-incrementing savepoint name.
     *
     * @return string
     */
    private function generateSavepointName(): string
    {
        return 'savepoint'.$this->savepointNumber++;
    }

    /**
     * Creates a savepoint.
     *
     * @param string $name
     * @return bool
     */
    private function createSavepoint(string $name): bool
    {
        // The DB steadfastly refuses to allow me to use bound params in
        // savepoint queries.
        // Inject instead -_- it will be safe as we directly control the input
        $this->connection->exec(
            sprintf('SAVEPOINT %s', $this->connection->quote($name, PDO::PARAM_STR))
        );
        return true;
    }

    /**
     * Rolls back to a savepoint.
     *
     * @param string $name
     * @return bool
     */
    private function rollbackToSavepoint(string $name): bool
    {
        $this->connection->exec(
            sprintf('ROLLBACK TO SAVEPOINT %s', $this->connection->quote($name, PDO::PARAM_STR))
        );
        return true;
    }

    /**
     * Releases a savepoint once it is no longer required.
     *
     * @param string $name
     * @return bool
     */
    private function releaseSavepoint(string $name): bool
    {
        $this->connection->exec(
            sprintf('RELEASE SAVEPOINT %s', $this->connection->quote($name, PDO::PARAM_STR))
        );
        return true;
    }
}
