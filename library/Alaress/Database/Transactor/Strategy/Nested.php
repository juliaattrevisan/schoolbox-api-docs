<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Database\Transactor\Strategy;

use Alaress\Database\Connector\IConnector;

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
     * @var IConnector
     */
    private $connector;

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
        return $this->connector->beginTransaction();
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
        return $this->connector->commit();
    }

    /**
     * @inheritdoc
     */
    public function rollback(): bool
    {
        return $this->connector->rollBack();
    }
}
