<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Database\Transactor\Strategy;

use PDO;

/**
 * Dummy strategy for database transaction handling.
 *
 * This strategy doesn't actually connect to a database; it only tracks the
 * nesting level that the database would be in.
 */
class Dummy implements IStrategy
{
    /**
     * Nesting level
     *
     * @var int
     */
    private $nestingLevel = 0;

    /**
     * Ignored; this doesn't connect to a database
     *
     * @param PDO $connection
     * @return self
     */
    public function setConnection(PDO $connection): IStrategy
    {
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function beginTransaction(): bool
    {
        $this->nestingLevel++;
        return true;
    }

    /**
     * @inheritdoc
     */
    public function inTransaction(): bool
    {
        return $this->nestingLevel > 0;
    }

    /**
     * @inheritdoc
     */
    public function commit(): bool
    {
        if ($this->inTransaction()) {
            $this->nestingLevel--;
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function rollback(): bool
    {
        if ($this->inTransaction()) {
            $this->nestingLevel--;
        }
        return true;
    }
}
