<?php

/**
 * Base connector class
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Database\Connector;

use Alaress\Database\Broker\IBroker;
use Alaress\Database\Iterator\IIterator;
use Alaress\Database\Predicate\Builder;
use Alaress\Database\Quoter\IQuoter;
use Alaress\Database\Statement\FunctionCall\IFunctionCall;
use Alaress\Database\Statement\IStatement;
use Alaress\Database\Statement\Query\IQuery;
use Alaress\Database\Statement\StoredProcedure\IStoredProcedure;
use Alaress\Database\Transactor\ITransactor;
use Alaress\Sentry\Tracing\Database\TracedPdoStatement;
use Alaress\Sentry\Tracing\Tracer;
use PDOStatement;
use SensitiveParameter;

class Base implements IConnector
{
    /** @var IBroker */
    protected $broker;

    /** @var ITransactor */
    protected $transactor;

    /**
     * Object for quoting/escaping string literals.
     */
    private IQuoter $quoter;

    /** @var array */
    protected $options;

    /** @var string */
    protected $dsn;

    /** @var string */
    protected $hostname;

    /** @var string */
    protected $database;

    /** @var string */
    protected $username;

    /** @var string */
    protected $password;

    /** @var string $error Last reported error */
    protected $error;

    /** @var string $error Last reported error code */
    protected $errorCode;

    /**
     * Connector constructor
     *
     * @param IBroker     $broker     Type specific broker
     * @param ITransactor $transactor Transactor appropriate for this broker
     * @param IQuoter     $quoter     Quoter appropriate for this broker
     */
    public function __construct(IBroker $broker, ITransactor $transactor, IQuoter $quoter)
    {
        $this->broker     = $broker;
        $this->transactor = $transactor;
        $this->quoter     = $quoter;
    }


    /**
     * Connect to the database
     *
     * @param string $hostname
     * @param string $database
     * @param string $username
     * @param string $password
     * @param array  $options Database connection options
     * @return bool
     * @throws \Alaress\Database\Exception
     */
    public function connect(
        string $hostname,
        string $database,
        string $username,
        #[SensitiveParameter] string $password,
        array $options = [],
    ): bool {
        $this->options = $options;
        $this->username = $username;
        $this->password = $password;
        $this->hostname = $hostname;
        $this->database = $database;

        $this->broker->setConnector($this);
        if ($this->broker->connect($options)) {
            $this->transactor->getStrategy()->setConnection($this->broker->getPdo());
            return true;
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function enableMultipleActiveResultSets()
    {
        $this->broker->enableMultipleActiveResultSets();
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function disableMultipleActiveResultSets()
    {
        $this->broker->disableMultipleActiveResultSets();
        return $this;
    }

    /**
     * @inheritdoc
     *
     * @param string $timezone A valid timezone
     */
    public function setTimezone($timezone)
    {
        $this->broker->setTimezone($timezone);
        return $this;
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Getters for the immutable properties
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function getHostname()
    {
        // Appeasing CI which doesn't seem to like this being done inline with the return statement

        $hostnameArray = explode(":", $this->hostname, 2);
        return $hostnameArray[0];
    }

    /**
     * @inheritdoc
     */
    public function getPort()
    {
        $hostnameArray = explode(":", $this->hostname, 2);

        if (count($hostnameArray) == 2) {
            return $hostnameArray[1];
        } else {
            return null;
        }
    }

    /**
     * @inheritdoc
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @inheritdoc
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @inheritdoc
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @inheritdoc
     */
    public function errorInfo()
    {
        return $this->error;
    }

    /**
     * @inheritdoc
     */
    public function errorCode()
    {
        return $this->errorCode;
    }

    /**
     * @inheritdoc
     */
    public function getCollector()
    {
        return $this->broker->getCollector();
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Serialization functions
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Array representation of object
     *
     * @return array
     */
    public function __serialize(): array
    {
        // We serialize just the settings of the database connection
        // TODO it can't be safe to store a password in a serialized object!
        return [
            'hostname' => $this->hostname,
            'username' => $this->username,
            'password' => $this->password,
            'database' => $this->database,
            'brokerClass' => get_class($this->broker)
        ];
    }

    /**
     * Constructs the object
     *
     * @param array $serialized
     *
     * @return void
     */
    public function __unserialize(array $serialized): void
    {
        // We load the settings back into the object
        $this->hostname = $serialized['hostname'];
        $this->username = $serialized['username'];
        $this->password = $serialized['password'];
        $this->database = $serialized['database'];

        // Create the correct broker class
        $this->broker = new $serialized['brokerClass']();
        $this->broker->setConnector($this);
        $this->broker->connect();
    }

    /**
     * @inheritdoc
     */
    public function reconnect($options = null)
    {
        if (is_array($options)) {
            $this->options = $options;
        }

        $this->broker->disconnect();
        return $this->broker->connect($this->options);
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Database statement generators
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function prepareQuery($sql, $parameters = [])
    {
        $statement = $this->broker->prepareQuery($sql);

        if (is_array($parameters)) {
            $statement->bindInputs($parameters);
        }

        return $statement;
    }

    /**
     * @inheritdoc
     */
    public function prepare(string $statement, array $driverOptions = []): PDOStatement
    {
        // Create a wrapper around a PDO statement here
        return new TracedPdoStatement(
            $this->broker->prepare($statement, $driverOptions),
            $this->broker->getDriver(),
            Tracer::getDefaultTrace(),
        );
    }

    /**
     * @inheritdoc
     */
    public function execute($sql)
    {
        return $this->prepareQuery($sql)->execute();
    }

    /**
     * @inheritdoc
     */
    public function executeDirectly(string $sql): bool
    {
        // This needs to be run directly via exec(),
        // but should be prepared anyway
        return Tracer::getDefaultTrace()->runWithinSpan(
            fn () => $this->broker->getPdo()->exec($sql) !== false,
            'db.sql.query',
            'auto.db',
            data: ['db.system' => $this->broker->getDriver(),],
        );
    }

    /**
     * @inheritdoc
     */
    public function prepareFunctionCall($function, $inputs = null, &$outputs = null, &$return = null)
    {
        /** @var IStoredProcedure $statement */
        $statement = $this->broker->prepareFunctionCall($function);

        if ($inputs != null) {
            $statement->bindInputs($inputs);
        }

        if ($outputs != null) {
            $statement->bindOutputs($outputs);
        }

        if ($return != null) {
            $statement->bindReturnValue($return);
        }

        return $statement;
    }

    /**
     * @inheritdoc
     */
    public function executeFunctionCall($function, $inputs = null, &$outputs = null, &$return = null)
    {
        /** @var IFunctionCall $function */
        $function = $this->prepareFunctionCall($function, $inputs, $outputs, $return);
        $function->execute();
        return $function;
    }
    /**
     * @inheritdoc
     */
    public function prepareStoredProcedure($procedure, $inputs = null, &$outputs = null, &$return = null)
    {
        /** @var IStoredProcedure $statement */
        $statement = $this->broker->prepareStoredProcedure($procedure);

        if ($inputs != null) {
            $statement->bindInputs($inputs);
        }

        if ($outputs != null) {
            $statement->bindOutputs($outputs);
        }

        if ($return != null) {
            $statement->bindReturnValue($return);
        }

        return $statement;
    }

    /**
     * @inheritdoc
     */
    public function executeQuery($sql, $parameters = null)
    {
        /** @var IQuery $statement */
        $statement = $this->prepareQuery($sql, $parameters);
        $statement->execute();
        return $statement;
    }

    /**
     * @inheritdoc
     */
    public function getQueryIterator($sql, $parameters = null)
    {
        /** @var IQuery $statement */
        $statement = $this->prepareQuery($sql, $parameters);
        return $statement->getIterator();
    }

    /**
     * @inheritdoc
     */
    public function executeStoredProcedure($procedure, $inputs = null, &$outputs = null, &$return = null)
    {
        /** @var IStoredProcedure $statement */
        $statement = $this->prepareStoredProcedure($procedure, $inputs, $outputs, $return);
        $statement->execute();
        return $statement;
    }

    /**
     * @inheritdoc
     */
    public function getStoredProcedureIterator($procedure, $inputs = null, &$outputs = null, &$return = null)
    {
        /** @var IStoredProcedure $statement */
        $statement = $this->prepareStoredProcedure($procedure, $inputs, $outputs, $return);
        return $statement->getIterator();
    }

    /**
     * @inheritdoc
     */
    public function lastInsertId($name = null)
    {
        return $this->broker->getPdo()->lastInsertId($name);
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Post processor callback stuff
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function addPostProcessor($callable)
    {
        $this->broker->addPostProcessor($callable);
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Database transaction functions
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function transact(callable $callback)
    {
        $this->transactor->transact($callback);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function onPersist(callable $callback)
    {
        $this->transactor->onPersist($callback);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function beginTransaction()
    {
        return $this->transactor->beginTransaction();
    }

    /**
     * @inheritdoc
     */
    public function inTransaction()
    {
        return $this->transactor->inTransaction();
    }

    /**
     * @inheritdoc
     */
    public function commit()
    {
        return $this->transactor->commit();
    }

    /**
     * @inheritdoc
     */
    public function rollBack()
    {
        return $this->transactor->rollBack();
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Lazy temporary clutch to build IN portion of queries, should integrate this things into the
    // predicate builder
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * @var int $count
     */
    private $count = 0;

    /**
     * @inheritdoc
     */
    public function prepareInBindings($externalIds, $prefix = 'param')
    {
        if (!is_array($externalIds)) {
            $externalIds = [$externalIds];
        }

        $sql = "";
        $bindings = [];

        foreach ($externalIds as $externalId) {
            if (!empty($sql)) {
                $sql .= ',';
            }

            $sql .= ":{$prefix}{$this->count}";
            $bindings[":{$prefix}{$this->count}"] = $externalId;

            $this->count++;
        }

        return [
            'sql' => $sql,
            'bindings' => $bindings
        ];
    }

    /**
     * @inheritdoc
     */
    public function buildPredicateBuilder($table)
    {
        return new Builder($this->quoter, $table);
    }

    /**
     * @inheritdoc
     */
    public function getQuoter(): IQuoter
    {
        return $this->quoter;
    }


    /**
     * Temporary MSSQL methods
     */

    /**
     * @inheritdoc
     */
    public function getRow(string $sql, array $parameters = null)
    {
        return $this->executeQuery($sql, $parameters)->fetch();
    }

    /**
     * @inheritdoc
     */
    public function getOne(string $sql, array $parameters = null)
    {
        return $this->executeQuery($sql, $parameters)->fetchColumn();
    }

    /**
     * @inheritdoc
     */
    public function getAll(string $sql, array $parameters = null): array
    {
        return $this->executeQuery($sql, $parameters)->fetchAll();
    }

    /**
     * @inheritdoc
     */
    public function getIterator(string $sql, array $parameters = null): IIterator
    {
        return $this->getQueryIterator($sql, $parameters);
    }

    /**
     * @inheritdoc
     */
    public function query(string $sql, array $parameters = null): IStatement
    {
        $result = $this->prepareQuery($sql, $parameters);
        $result->execute();
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function prepareProcedure(string $name): IStoredProcedure
    {
        return $this->prepareStoredProcedure($name);
    }

    /**
     * @inheritdoc
     */
    public function legacyQuote($value): string
    {
        return $this->broker->getPdo()->quote($value);
    }

    /**
     * @inheritdoc
     */
    public function trimAll(array $rowset): array
    {
        return array_map([$this, 'trimRow'], $rowset);
    }

    /**
     * @inheritdoc
     */
    public function trimRow(array $row): array
    {
        return array_map([$this, 'trimField'], $row);
    }

    /**
     * @inheritdoc
     */
    public function trimField($fieldValue)
    {
        return isset($fieldValue) ? trim($fieldValue) : null;
    }


    /**
     * Methods copied from DeprecatedDatabase: for sanity's sake these need to
     * remain copied here.
     */


    /**
     * @inheritdoc
     */
    public function insert(string $table, array $array): bool
    {
        if (!($table && is_array($array))) {
            return false;
        }

        $names  = [];
        $values = [];
        $params = [];
        $quoter = $this->getQuoter();
        foreach ($array as $key => $value) {
            $names[] = $quoter->quoteIdentifier($key);
            if (!is_array($value)) {
                $values[] = '?';
                $params[] = $value;
            } elseif (isset($value['function'])) {
                $values[] = ($value['function'] . '(?)');
                $params[] = $value['value'];
            }
        }
        $names  = implode(',', $names);
        $values = implode(',', $values);

        $query = "INSERT INTO {$quoter->quoteIdentifier($table)} ($names) VALUES ($values)";
        $this->query($query, $params);
        return true;
    }

    /**
     * @inheritdoc
     */
    public function update(string $table, array|string $identifier, array $array): bool
    {
        if (!$table) {
            return false;
        }

        if (is_array($identifier)) {
            foreach ($identifier as $ident) {
                if (!$array[$ident]) {
                    return false;
                }
            }
        } else {
            if (!$array[$identifier]) {
                return false;
            }

            $identifier = [$identifier];
        }

        $set    = [];
        $params = [];
        $quoter = $this->getQuoter();
        foreach ($array as $key => $value) {
            if (!in_array($key, $identifier)) {
                if (!is_array($value)) {
                    $set[]    = "{$quoter->quoteIdentifier($key)} = ?";
                    $params[] = $value;
                } elseif (isset($value['function'])) {
                    $set[]    = "{$quoter->quoteIdentifier($key)} = {$value['function']}(?)";
                    $params[] = $value;
                }
            }
        }
        $set   = implode(',', $set);
        $query = "UPDATE {$quoter->quoteIdentifier($table)} SET $set WHERE 1=1";

        foreach ($identifier as $ident) {
            $query .= " AND {$quoter->quoteIdentifier($ident)} = ?";
            $params[] = $array[$ident];
        }

        $this->query($query, $params);
        return true;
    }

    /**
     * @inheritdoc
     */
    public function delete(string $table, string $identifier, string|array $value): bool
    {
        $query  = "DELETE FROM {$this->getQuoter()->quoteIdentifier($table)} WHERE 1 != 1 ";
        $params = [];
        if (is_array($value)) {
            foreach ($value as $item) {
                $query .= " OR {$this->getQuoter()->quoteIdentifier($identifier)} = ?";
                $params[] = $item;
            }
        } else {
            $query .= "OR {$this->getQuoter()->quoteIdentifier($identifier)} = ?";
            $params[] = $value;
        }
        $this->query($query, $params);
        return true;
    }
}
