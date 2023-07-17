<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Database\Connector;

use PDOException;

class ReadReplicatedProxy extends Base
{
    /**
     * Default time (seconds) to wait for writes to be replicated to read
     * replicas.
     *
     * If a read happens less than this long after a write: send the query to
     * the write connection instead of to a read replica.
     */
    public const DEFAULT_REPLICATION_LAG_LEEWAY = 1000;

    /**
     * Current last write time (if any).
     *
     * @var float|null
     */
    private ?float $lastWriteTime = null;

    /**
     * Constructor
     *
     * @param IConnector $writeConnector           The connector to use for writes, and for reads whilst writes are in
     *                                             progress (e.g. there is a transaction) and for a configurable time
     *                                             afterward.
     * @param IConnector $readReplicaConnector     The read-replica connector this will be to the RDS load balancer: this
     *                                             is used for reads if the database is currently being or has just been written.
     * @param float      $replicationLagLeeway     A configurable time (in seconds) to wait for after a write is
     *                                             completed: any reads before this has elapsed go to the write
     *                                             connector, instead of a replica.
     */
    public function __construct(
        private IConnector $writeConnector,
        private IConnector $readReplicaConnector,
        private float $replicationLagLeeway = self::DEFAULT_REPLICATION_LAG_LEEWAY,
    ) {
    }

    /**
     * Last insert ID always comes from the write connector
     *
     * @param $name
     * @return false|string
     */
    public function lastInsertId($name = null)
    {
        return $this->writeConnector->lastInsertId($name);
    }

    /**
     * Transactions are always started on the write connector
     *
     * @return bool
     */
    public function beginTransaction()
    {
        return $this->writeConnector->beginTransaction();
    }

    /**
     * Upon committing a transaction: set the time of last write, if not
     * still in a transaction.
     *
     * @return bool
     */
    public function commit()
    {
        $result = $this->writeConnector->commit();
        if (!$this->inTransaction()) {
            $this->lastWriteTime = microtime(true);
        }
        return $result;
    }

    /**
     * Example for preparing a query against this proxy: use a read replica if
     * possible, otherwise use the write connector.
     *
     * The proxy is determined at preparation rather than execute time, so need
     * to use the query not long after preparation?
     *
     * @param $query
     * @param $parameters
     * @return \Alaress\Database\Statement\Query\IQuery
     */
    public function prepareQuery($query, $parameters = [])
    {
        return $this->determineConnector($query)->prepareQuery($query, $parameters);
    }

    private function determineConnector($query): IConnector
    {
        // In transaction? Use write connector
        if ($this->inTransaction()) {
            return $this->writeConnector;
        }

        // Not in transaction, but still waiting for replication lag?
        // Use write connector
        if (
            isset($this->lastWriteTime)
            && ($this->lastWriteTime + $this->replicationLagLeeway) < microtime(true)
        ) {
            return $this->writeConnector;
        }

        // Anything other than a SELECT statement? Use write connector

        // Otherwise: use one of the read connectors, selected however you like:
        // could use random selection, or round-robin, or however
        return $this->readReplicaConnector;
    }
}
