<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Database\Transactor\Strategy;

use Alaress\Database\Connector\IConnector;
use PDO;
use PDOStatement;

/**
 * Savepoint strategy for database transaction handling. This strategy
 * uses savepoints to emulate nested transactions for databases which don't
 * support nested transactions.
 */
class Savepoint implements IStrategy
{
    /**
     * A database connection
     *
     * @var IConnector
     */
    private $connector;

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
     * @var PDOStatement
     */
    private $createSavepointQuery;

    /**
     * @var PDOStatement
     */
    private $rollbackToSavepointQuery;

    /**
     * @var PDOStatement
     */
    private $releaseSavepointQuery;

    /**
     * Constructor
     *
     * @param IConnector $connector
     */
    public function __construct(IConnector $connector)
    {
        $this->connector = $connector;
    }

    /**
     * @inheritdoc
     */
    public function beginTransaction(): bool
    {
        // In a transaction? Don't begin another level
        if (!$this->inTransaction() && !$this->connector->beginTransaction()) {
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
        return $this->connector->inTransaction();
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
            return $this->connector->commit();
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
            return $this->connector->rollBack();
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
        if (!$this->createSavepointQuery) {
            $this->createSavepointQuery = $this->connector->prepareQuery('SAVEPOINT :name');
        }
        $this->createSavepointQuery->bindValue('name', $name, PDO::PARAM_STR);
        return $this->createSavepointQuery->execute();
    }

    /**
     * Rolls back to a savepoint.
     *
     * @param string $name
     * @return bool
     */
    private function rollbackToSavepoint($name): bool
    {
        if (!$this->rollbackToSavepointQuery) {
            $this->rollbackToSavepointQuery = $this->connector->prepareQuery('ROLLBACK TO SAVEPOINT :name');
        }
        $this->rollbackToSavepointQuery->bindValue('name', $name, \PDO::PARAM_STR);
        return $this->rollbackToSavepointQuery->execute();
    }

    /**
     * Releases a savepoint once it is no longer required.
     *
     * @param string $name
     * @return bool
     */
    private function releaseSavepoint($name): bool
    {
        if (!$this->releaseSavepointQuery) {
            $this->releaseSavepointQuery = $this->connector->prepareQuery('RELEASE SAVEPOINT :name');
        }
        $this->releaseSavepointQuery->bindValue('name', $name, \PDO::PARAM_STR);
        return $this->releaseSavepointQuery->execute();
    }
}
