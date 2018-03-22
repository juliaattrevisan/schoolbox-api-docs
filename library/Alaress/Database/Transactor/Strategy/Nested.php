<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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
