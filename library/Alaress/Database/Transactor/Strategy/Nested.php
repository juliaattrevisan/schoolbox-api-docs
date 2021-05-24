<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Database\Transactor\Strategy;

use PDO;

/**
 * Nested strategy for database transaction handling. This strategy doesn't
 * care if there's already a transaction open when `beginTransaction()` is
 * called.
 *
 * Assumes that the database supports nested transactions; notably, MySQL
 * doesn't.
 */
class Nested implements IStrategy
{
    /**
     * A database connection
     *
     * @var PDO
     */
    private $connection;

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
        return $this->connection->beginTransaction();
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
        return $this->connection->commit();
    }

    /**
     * @inheritdoc
     */
    public function rollback(): bool
    {
        return $this->connection->rollBack();
    }
}
