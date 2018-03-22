<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Database\Transactor\Strategy;

use Alaress\Database\Broker\IBroker;

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
     * A database connection broker
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
     * @inheritdoc
     */
    public function beginTransaction(): bool
    {
        return $this->broker->beginTransaction();
    }

    /**
     * @inheritdoc
     */
    public function inTransaction(): bool
    {
        return $this->broker->inTransaction();
    }

    /**
     * @inheritdoc
     */
    public function commit(): bool
    {
        return $this->broker->commit();
    }

    /**
     * @inheritdoc
     */
    public function rollback(): bool
    {
        return $this->broker->rollBack();
    }
}
