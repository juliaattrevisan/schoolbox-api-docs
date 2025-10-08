<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Database\Connector;

use Alaress\Database\Collector;
use Alaress\Database\Exception;
use Alaress\Database\Iterator\IIterator;
use Alaress\Database\Predicate\IBuilder;
use Alaress\Database\Quoter\IQuoter;
use Alaress\Database\Statement\FunctionCall\IFunctionCall;
use Alaress\Database\Statement\IStatement;
use Alaress\Database\Statement\Query\IQuery;
use Alaress\Database\Statement\StoredProcedure\IStoredProcedure;
use PDOException;
use PDOStatement;
use SensitiveParameter;

class ReadReplicatedProxy implements IConnector
{
    /**
     * Default time (seconds) to wait for writes to be replicated to read
     * replicas.
     *
     * If a read happens less than this long after a write: send the query to
     * the write connection instead of to a read replica.
     */
    public const DEFAULT_REPLICATION_LAG_LEEWAY = 1.000;

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

    /**
     * Helper proxy to work out which actual db connection we should be using
     *
     * @param string $query
     * @return IConnector
     */
    private function determineConnector(string $query = ''): IConnector
    {
        // In transaction? Use write connector
        if ($this->writeConnector->inTransaction()) {
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
        $isSelect = preg_match("/^([\(\s+]*SELECT)/i", $query);
        if (!$isSelect) {
            return $this->writeConnector;
        }

        // Otherwise: use one of the read connectors, selected however you like:
        // could use random selection, or round-robin, or however
        return $this->readReplicaConnector;
    }

    /**
     * Connect to database
     *
     * @param string $hostname
     * @param string $database
     * @param string $username
     * @param string $password
     * @param array  $options
     * @return bool
     */
    public function connect(string $hostname, string $database, string $username, #[SensitiveParameter] string $password, array $options = [],): bool
    {
        throw new Exception('Not supported.');
    }

    /**
     * Reconnect to the database (optionally- with new options)
     *
     * @param array|null $options
     * @return bool
     */
    public function reconnect($options = null)
    {
        throw new Exception('Not supported.');
    }

    /**
     * Enable the use of multiple active result sets (required if executing a query inside an iterator).
     * This may or may not be supported by all database connectors- where it is not it will just do nothing.
     *
     * @return $this
     */
    public function enableMultipleActiveResultSets()
    {
        $this->writeConnector->enableMultipleActiveResultSets();
        $this->readReplicaConnector->enableMultipleActiveResultSets();
        return $this;
    }

    /**
     * Disable the use of multiple active result sets (better performance)
     *
     * @return $this
     */
    public function disableMultipleActiveResultSets()
    {
        $this->writeConnector->disableMultipleActiveResultSets();
        $this->readReplicaConnector->disableMultipleActiveResultSets();
        return $this;
    }

    /**
     * Set the timezone on the all db connections (Note this is only appplicable on MySQL connections)
     *
     * @param string $timezone A valid timezone
     * @return $this
     */
    public function setTimezone($timezone)
    {
        $this->writeConnector->setTimezone($timezone);
        $this->readReplicaConnector->setTimezone($timezone);
        return $this;
    }

    /**
     * Executes a query and returns true/false to indicate success
     *
     * @param string $sql
     * @return bool
     * @throws \Alaress\Database\Exception
     */
    public function execute($sql)
    {
        return $this->determineConnector($sql)->execute($sql);
    }

    /**
     * Executes a query directly via a PDO connection, instead of preparing it
     * and then executing.
     *
     * This seems to be required for `SET` queries: they don't update the
     * session configuration if prepared and then run.
     *
     * @param string $sql
     * @return bool
     */
    public function executeDirectly(string $sql): bool
    {
        return $this->determineConnector($sql)->executeDirectly($sql);
    }

    /**
     * Convenience methods that prepares and executes a query with the supplied parameters
     *
     * @param string     $sql
     * @param null|array $parameters
     * @return IQuery|\Alaress\Database\Statement\Query\Base
     * @throws \Alaress\Database\Exception
     */
    public function executeQuery($sql, $parameters = null)
    {
        return $this->determineConnector($sql)->executeQuery($sql, $parameters);
    }

    /**
     * Retrieve an iterator for a SQL query
     *
     * @param string     $sql
     * @param null|array $parameters
     * @return IIterator
     */
    public function getQueryIterator($sql, $parameters = null)
    {
        return $this->determineConnector($sql)->getQueryIterator($sql, $parameters);
    }

    /**
     * Prepare database function call
     *
     * @param string     $function Name of the function to execute
     * @param null|array $inputs
     * @param null|array $outputs
     * @param null|array $return
     * @return IStoredProcedure
     */
    public function prepareFunctionCall($function, $inputs = null, &$outputs = null, &$return = null)
    {
        return $this->writeConnector->prepareFunctionCall($function, $inputs, $outputs, $return);
    }

    /**
     * Convenience method to prepare and execute a function call with the supplied parameters
     *
     * @param string     $function
     * @param null|array $inputs
     * @param null|array $outputs
     * @param null|array $return
     * @return IFunctionCall
     */
    public function executeFunctionCall($function, $inputs = null, &$outputs = null, &$return = null)
    {
        return $this->writeConnector->executeFunctionCall($function, $inputs, $outputs, $return);
    }

    /**
     * Prepare database stored procedure
     *
     * @param string     $procedure Name of the stored procedure to execute
     * @param null|array $inputs
     * @param null|array $outputs
     * @param null|array $return
     * @return IStoredProcedure
     */
    public function prepareStoredProcedure($procedure, $inputs = null, &$outputs = null, &$return = null)
    {
        return $this->writeConnector->prepareStoredProcedure($procedure, $inputs, $outputs, $return);
    }

    /**
     * Convenience method to prepare and execute a stored procedure with the supplied parameters
     *
     * @param string     $procedure
     * @param null|array $inputs
     * @param null|array $outputs
     * @param null|array $return
     * @return IStoredProcedure
     */
    public function executeStoredProcedure($procedure, $inputs = null, &$outputs = null, &$return = null)
    {
        return $this->writeConnector->executeStoredProcedure($procedure, $inputs, $outputs, $return);
    }

    /**
     * Retrieve an iterator for a stored procedure
     *
     * @param string     $procedure
     * @param null|array $inputs
     * @param null|array $outputs
     * @param null|array $return
     * @return IIterator
     */
    public function getStoredProcedureIterator($procedure, $inputs = null, &$outputs = null, &$return = null)
    {
        return $this->writeConnector->getStoredProcedureIterator($procedure, $inputs, $outputs, $return);
    }

    /**
     * Wraps `$callback` in a transaction.
     *
     * @param callable $callback
     * @return IConnector
     * @throws \Throwable
     */
    public function transact(callable $callback)
    {
        $this->lastWriteTime = microtime(true);
        return $this->writeConnector->transact($callback);
    }

    /**
     * If not in a transaction, call `$callback` immediately.
     *
     * Otherwise, queue `$callback` to be called only if the outermost nesting
     * level is committed.
     *
     * @param callable $callback
     * @return IConnector
     */
    public function onPersist(callable $callback)
    {
        $this->lastWriteTime = microtime(true);
        return $this->writeConnector->onPersist($callback);
    }

    /**
     * Check if we are in a database transaction
     *
     * @return bool Returns TRUE if a transaction is currently active, and FALSE if not
     */
    public function inTransaction()
    {
        return $this->writeConnector->inTransaction();
    }

    /**
     * Rollback current transaction
     *
     * @return bool Returns TRUE on success or FALSE on failure
     * @throws PDOException if there is no active transaction
     */
    public function rollBack()
    {
        return $this->writeConnector->rollBack();
    }

    /**
     * Returns last error message from database connection
     *
     * @return string
     */
    public function errorInfo()
    {
        throw new Exception('Not supported.');
    }

    /**
     * Returns last error code from database connection
     *
     * @return string
     */
    public function errorCode()
    {
        throw new Exception('Not supported.');
    }

    /**
     * Return hostname used for connection
     *
     * @return string
     */
    public function getHostname()
    {
        throw new Exception('Not supported.');
    }

    /**
     * Return port used for connection
     *
     * @return string
     */
    public function getPort()
    {
        throw new Exception('Not supported.');
    }

    /**
     * Return username used for connection
     *
     * @return string
     */
    public function getUsername()
    {
        throw new Exception('Not supported.');
    }

    /**
     * Return password used for connection
     *
     * @return string
     */
    public function getPassword()
    {
        throw new Exception('Not supported.');
    }

    /**
     * Return database name used for connection
     *
     * @return string
     */
    public function getDatabase()
    {
        throw new Exception('Not supported.');
    }

    /**
     * Return database collector for debugging
     *
     * @return Collector
     */
    public function getCollector()
    {
        return $this->readReplicaConnector->getCollector();
    }

    /**
     * Add a post processor to this connection, callable must accept either a string/array and return the same type
     * after whatever processing is required.
     *
     * Add processor to both connectors, but return the one created on the read conenction.
     *
     * @param callable $callable
     */
    public function addPostProcessor($callable)
    {
        $this->writeConnector->addPostProcessor($callable);
        return $this->readReplicaConnector->addPostProcessor($callable);
    }

    /**
     * Create an array with SQL and matching bindings for an IN query
     * TODO: Investigate that predicate builder thing
     *
     * @param string|array $externalIds
     * @param string       $prefix
     * @return mixed
     */
    public function prepareInBindings($externalIds, $prefix = 'param')
    {
        return $this->readReplicaConnector->prepareInBindings($externalIds, $prefix);
    }

    /**
     * Returns a predicate builder for this connector's database.
     *
     * @param string $table The table to build the predicate builder for
     * @return IBuilder
     */
    public function buildPredicateBuilder($table)
    {
        return $this->readReplicaConnector->buildPredicateBuilder($table);
    }

    /**
     * Retrieve this connector's quoter.
     *
     * @return IQuoter
     */
    public function getQuoter(): IQuoter
    {
        return $this->readReplicaConnector->getQuoter();
    }
    /**
 * Temporary implementation of `\MSSQL::getRow`, to ease transition.
 *
 * @param string     $sql
 * @param array|null $parameters
 * @return array|bool The row, or false if no row is found
 * @deprecated Replace with `$this->executeQuery($sql, $parameters)->fetch()`
 */
    public function getRow(string $sql, ?array $parameters = null)
    {
        return $this->determineConnector($sql)->getRow($sql, $parameters);
    }

    /**
     * Temporary implementation of `\MSSQL::getOne`, to ease transition.
     *
     * @param string     $sql
     * @param array|null $parameters
     * @return mixed
     * @deprecated Replace with `$this->executeQuery($sql, $parameters)->fetchColumn()`
     */
    public function getOne(string $sql, ?array $parameters = null)
    {
        return $this->determineConnector($sql)->getOne($sql, $parameters);
    }

    /**
     * Temporary implementation of `\MSSQL::getAll`, to ease transition.
     *
     * @param string     $sql
     * @param array|null $parameters
     * @return array
     * @deprecated Replace with `$this->executeQuery($sql, $parameters)->fetchAll()`
     */
    public function getAll(string $sql, ?array $parameters = null): array
    {
        return $this->determineConnector($sql)->getAll($sql, $parameters);
    }

    /**
     * Temporary implementation of `\MSSQL::getIterator`, to ease transition.
     *
     * @param string     $sql
     * @param array|null $parameters
     * @return IIterator
     * @deprecated Replace with `$this->executeQuery($sql, $parameters)->getIterator()`
     */
    public function getIterator(string $sql, ?array $parameters = null): IIterator
    {
        return $this->determineConnector($sql)->getIterator($sql, $parameters);
    }

    /**
     * Temporary implementation of `\MSSQL::query`, to ease transition.
     *
     * @param string     $sql
     * @param array|null $parameters
     * @return IStatement
     * @deprecated Replace with `$this->executeQuery($sql, $parameters)`
     */
    public function query(string $sql, ?array $parameters = null): IStatement
    {
        return $this->determineConnector($sql)->query($sql, $parameters);
    }

    /**
     * Temporary implementation of `\MSSQL::prepareProcedure`, to ease transition.
     *
     * @param string $name
     * @return IStoredProcedure
     * @deprecated Replace with `$this->prepareStoredProcedure($name)`
     */
    public function prepareProcedure(string $name): IStoredProcedure
    {
        return $this->prepareStoredProcedure($name);
    }

    /**
     * Temporary implementation of `\MSSQL::smartQuote`, to ease transition:
     * this function escapes a value for safe usage in a query, and wraps in
     * quotes so that it can be interpolated directly into a string.
     *
     * Renamed to `legacyQuote` so that its legacy nature is well-communicated.
     *
     * @param mixed $value
     * @return string
     * @deprecated Replace with query parameters
     */
    public function legacyQuote($value): string
    {
        return $this->readReplicaConnector->legacyQuote($value);
    }

    /**
     * Temporary implementation of `\MSSQL::trimAll`, to ease transition.
     *
     * @deprecated replace with a helper class? field value massage isn't really a responsibility of the connector
     * @param array $rowset
     * @return array
     */
    public function trimAll(array $rowset): array
    {
        return $this->readReplicaConnector->trimAll($rowset);
    }

    /**
     * Temporary implementation of `\MSSQL::trimRow`, to ease transition.
     *
     * @deprecated replace with a helper class? field value massage isn't really a responsibility of the connector
     * @param array $row )
     * @return array
     */
    public function trimRow(array $row): array
    {
        return $this->readReplicaConnector->trimRow($row);
    }

    /**
     * Temporary implementation of `\MSSQL::trimField`, to ease transition.
     *
     * @deprecated replace with a helper class? field value massage isn't really a responsibility of the connector
     * @param mixed $fieldValue
     * @return mixed
     */
    public function trimField($fieldValue)
    {
        return $this->readReplicaConnector->trimField($fieldValue);
    }

    /**
     * Temporary implementation of `\Alaress\IDatabase::prepare`, to ease
     * transition.
     *
     * @param string $statement
     * @param array  $driverOptions
     * @return PDOStatement
     * @deprecated should use `prepareQuery` instead
     *
     */
    public function prepare(string $statement, array $driverOptions = []): PDOStatement
    {
        return $this->determineConnector($statement)->prepare($statement, $driverOptions);
    }

    /**
     * Temporary implementation of `\Database::insert`, to ease transition.
     *
     * @param string $table Insert into this table.
     * @param array  $array Insert this set of key-value pairs into this table's
     *                      fields.
     * @return bool
     * @deprecated use \Schoolbox\Mapper::save()
     *
     */
    public function insert(string $table, array $array): bool
    {
        $this->lastWriteTime = microtime(true);
        return $this->writeConnector->insert($table, $array);
    }

    /**
     * Temporary implementation of `\Database::update`, to ease transition.
     *
     * @param string       $table      Update this table.
     * @param array|string $identifier Keys for `$table`.
     * @param array        $array      Update this set of key-value pairs in
     *                                 this table.
     *                                 Any field which is in `$identifier` will
     *                                 be used in the WHERE part of the query.
     *
     * @return bool
     * @deprecated use \Schoolbox\Mapper::save()
     *
     */
    public function update(string $table, array|string $identifier, array $array): bool
    {
        $this->lastWriteTime = microtime(true);
        return $this->writeConnector->update($table, $identifier, $array);
    }

    /**
     * Temporary implementation of `\Database::delete`, to ease transition.
     *
     * @param string       $table       Delete from this table.
     * @param string       $identifier  Key for `$table`.
     * @param string|array $value       Delete from `$table` where the column
     *                                  `$identifier` matches any of these
     *                                  values.
     * @return bool
     * @deprecated use \Schoolbox\Mapper::delete()
     *
     */
    public function delete(string $table, string $identifier, array|string $value): bool
    {
        $this->lastWriteTime = microtime(true);
        return $this->writeConnector->delete($table, $identifier, $value);
    }
}
