<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 *
 */

use Alaress\Cache;
use Alaress\Database\Quoter\IQuoter;
use Alaress\Database\Quoter\Mysql\Quoter as MysqlQuoter;
use Alaress\Database\Transactor\Base as Transactor;
use Alaress\Database\Transactor\Strategy\Savepoint as TransactionStrategy;
use Alaress\DebugBar\Instance as DebugBar;
use Alaress\IDatabase;
use DebugBar\DataCollector\PDO as DebugPDO;

/**
 * Database connection
 *
 * @deprecated use IConnector objects instead
 */
class Database implements IDatabase, Serializable
{
    /**
     * An instance of database
     *
     * @var Database
     */
    private static $instance;

    /**
     * Database source name
     *
     * @var String
     */
    private $dsn;

    /**
     * Username for authenticated connection
     *
     * @var String
     */
    private $username;

    /**
     * Password for authenticated connection
     *
     * @var String
     */
    private $password;

    /**
     * An array of additional PDO connection options
     *
     * @var array
     */
    private $options;

    /**
     * The database connection, and instance of PDO
     *
     * @var PDO|null
     */
    private $connection = null;

    /**
     * The transaction handler
     *
     * @var Transactor
     */
    private $transactor;

    /** @var Cache|PDOStatement[] */
    private $qCache = [];

    /** @var IQuoter */
    private $quoter;

    /**
     * Returns the singleton instance of Database. If it doesn't exist it is instantiated.
     *
     * @static
     * @return Database
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Explicitly dis- and re-connect the singleton database connection
     */
    public static function reinitialise()
    {
        self::$instance->reconnect();
    }


    /**
     */
    public function __construct()
    {
        $this->qCache = new Cache(
            function ($statement, array $driverOptions = []) {
                return $this->connection->prepare($statement, $driverOptions);
            }
        );
        $this->quoter = new MysqlQuoter();
    }


    /**
     * Establishes the database connection
     *
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @param array $options
     * @throws Schoolbox\External\Exception
     * @return Bool
     */
    public function connect($dsn, $username, $password, $options = array())
    {
        if (empty($dsn) || !is_string($dsn)) {
            return false;
        }

        /* Set connection parameters */
        $this->dsn = $dsn;
        $this->username = $username;
        $this->password = $password;
        $this->options = $options;

        /* Create PDO connection */
        try {
            $this->connection = new PDO(
                $dsn,
                $username,
                $password,
                array(
                    PDO::ATTR_PERSISTENT         => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                )
            );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->setTimezone($this->fetchTimezone());
        } catch (PDOException $exception) {
            if (isset($options['throw']) && $options['throw']) {
                throw new \Schoolbox\External\Exception($exception->getMessage(), $exception->getCode(), $exception);
            }
            $this->error($exception);
        }

        // Create transactor, which depends on connection
        $this->transactor = new Transactor(new TransactionStrategy());
        $this->transactor->getStrategy()->setConnection($this->connection);

        return true;
    }

    /**
     * Re-establishes a lost database connection if the reason supplied by $e is acceptable.
     *
     * @throws PDOException
     * @return bool
     */
    public function reconnect()
    {
        // try to call some sort of PDO destructor
        $this->connection = null;
        unset($this->connection);
        return $this->connect($this->dsn, $this->username, $this->password, $this->options);
    }

    /**
     * Attempt to set the connection's timezone.
     *
     * @param string $timezone A valid timezone
     * @return bool
     * @throws PDOException if the timezone can't be set
     */
    public function setTimezone($timezone)
    {
        return $this->connection
            ->prepare("SET time_zone = ?")
            ->execute([$timezone]);
    }

    /**
     * Fetch the timezone from the database we are connecting to
     *
     * @return string Timezone
     */
    private function fetchTimezone()
    {
        $statement = $this->connection->prepare("SELECT `value` FROM `config` WHERE name LIKE 'date_timezone'");
        $statement->execute();
        $result = $statement->fetchColumn();
        return $result;
    }

    /**
     * Retrieves the query collector from this connection.
     *
     * Will fail if $this->options[debug] is off.
     *
     * @return \DebugBar\DataCollector\PDO\PDOCollector
     */
    public function getCollector()
    {
        $this->connection = new DebugPDO\TraceablePDO($this->connection);
        return new DebugPDO\PDOCollector($this->connection);
    }

    /**
     * Wrapper for PDO::exec
     * PDO::exec() executes an SQL statement in a single function call, returning the number
     * of rows affected by the statement.
     *
     * @param String $statement
     * @return Integer
     */
    public function exec($statement)
    {
        $stmt = $this->prepare($statement);
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Wrapper for PDO::prepare
     * PDO::prepare prepares a statement for execution and returns a PDOStatement object.
     *
     * @param string $statement
     * @param array $driverOptions
     * @return PDOStatement
     */
    public function prepare($statement, array $driverOptions = array())
    {
        return $this->qCache->get(sha1($statement), [$statement, $driverOptions]);
    }

    /**
     * Wrapper for PDO::query
     * PDO::query() executes an SQL statement in a single function call, returning the result set (if any)
     * returned by the statement as a PDOStatement object.
     *
     * @param string $statement
     * @param array $args
     * @return PDOStatement
     */
    public function query($statement, array $args = null)
    {
        $stmt = $this->prepare($statement);
        if ($args) {
            $stmt->execute($args);
        } else {
            $stmt->execute();
        }
        return $stmt;
    }

    /**
     * Wrapper for PDO::quote
     *
     * @param string $string
     * @param int $parameter_type
     * @return string
     */
    public function quote($string, $parameter_type = PDO::PARAM_STR)
    {
        return $this->connection->quote($string, $parameter_type);
    }

    /**
     * @inheritdoc
     */
    public function getQuoter(): IQuoter
    {
        return $this->quoter;
    }

    /**
     * Tries to create a unique parameter name
     *
     * @param string $param_name The base name to attempt to add
     * @param array $existing_params The existing list of parameters to add to
     *                                a query
     *
     * @return string The param name, or null if it couldn't be created after
     *                100 attempts.
     */
    public function createUniqueParameterName($param_name, $existing_params)
    {
        $new_name = $param_name;
        $count = 0;
        while ($count < 100) {
            if (!array_key_exists($new_name, $existing_params)) {
                return $new_name;
            }
            $new_name = $param_name . '_' . $count;
            $count++;
        }
        return null;
    }

    /**
     * Wrapper for PDO::lastInsertId
     * Returns the ID of the last inserted row, or the last value from a sequence object, depending
     * on the underlying driver. For example, PDO_PGSQL() requires you to specify the name of a sequence object
     * for the name parameter.
     *
     * @param null $name
     * @return string
     */
    public function lastInsertId($name = null)
    {

        return $this->connection->lastInsertId($name);
    }

    /**
     * @inheritdoc
     */
    public function transact(callable $callback)
    {
        return $this->transactor->transact($callback);
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
     * Wrapper for PDO::beginTransaction
     * Initiates a transaction
     *
     * Usage example:
     * <code>
     * $db->beginTransaction();
     * try {
     *     // a set of updates which must be atomic
     * } catch (\Throwable $e) {
     *     // Rollback, and then rethrow to ensure that commit() is not called
     *     $db->rollback();
     *     throw $e;
     * }
     * // Commit only if the try/catch block has completed without error
     * $db->commit();
     * </code>
     *
     * @return bool true on success or false on failure.
     */
    public function beginTransaction()
    {
        return $this->transactor->beginTransaction();
    }

    /**
     * Wrapper for PDO::commit
     * Commits a transaction
     * @link http://php.net/manual/en/pdo.commit.php
     *
     * @return bool true on success or false on failure.
     */
    public function commit()
    {
        return $this->transactor->commit();
    }

    /**
     * Wrapper for PDO::rollBack
     * Rolls back a transaction
     * @link http://php.net/manual/en/pdo.rollback.php
     *
     * @return bool true on success or false on failure.
     */
    public function rollBack()
    {
        return $this->transactor->rollback();
    }

    /**
     * Wrapper for PDO::inTransaction
     * Checks if inside a transaction
     * @link http://php.net/manual/en/pdo.intransaction.php
     *
     * @return bool true if a transaction is currently active, and false if not.
     */
    public function inTransaction()
    {
        return $this->transactor->inTransaction();
    }

    /**
     * Returns whether or not the database connection has been initialised.
     *
     * @return bool
     */
    public function isConnected()
    {
        if (isset($this->connection) && !empty($this->connection)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Prints the PDOException error message and dies
     *
     * If within a web request, give a response code that indicates the class of DB error:
     * * 598 - DB offline
     * * 597 - other fatal DB error
     *
     * @param PDOException $exception
     * @return void
     */
    private function error(PDOException $exception): void
    {
        if (PHP_SAPI !== 'cli') {
            $databaseOfflineErrors = [
                "SQLSTATE[HY000] [2002] No such file or directory",  // on-premises mariadb
                "SQLSTATE[HY000] [2002] Connection refused",         // cloud-saas RDS mariadb
            ];
            // Does the error message indicate the database is simply offline?
            if (in_array($exception->getMessage(), $databaseOfflineErrors, true)) {
                http_response_code(598);
                include __DIR__ . "/../templates/core/error/598-database-offline.html";
                exit;
            }
            http_response_code(597);
        }
        die("Database Error: {$exception->getMessage()}");
    }

    /**
     * String representation of object
     *
     * @return string
     */
    public function serialize()
    {
        return null;
    }

    /**
     * Constructs the object
     *
     * @param string $serialized
     * @return void
     */
    public function unserialize($serialized)
    {
        $this->connect(SQL_dsn, SQL_user, SQL_passwd, array('debug' => DebugBar::isEnabled()));
    }


    /// Methods copied from DeprecatedDatabase: will be removed in this PR ///


    /**
     * Quote variable to make safe for sql use
     *
     * @deprecated
     *
     * @param $value
     *
     * @return string
     */
    public function smartQuote($value)
    {
        $mysqlFuncs = array(
            'NULL',
            'NOW()',
        );

        /* If any non-digits found, assume string. But this might not work for float
         * or double values, or exponential representations.
         */
        // Quote if not integer or special
        if (!in_array($value, $mysqlFuncs)) {
            $value = $this->quote($value);
        }

        return $value;
    }

    /**
     * Inserts an array into table (does all proper security checks to ensure safe)
     *
     * @deprecated use \Schoolbox\Mapper::save()
     *
     * @param string $table     Insert into this table
     * @param array  $array     Insert this set of key-value pairs into this
     *                          table's fields
     * @param array  $nowFields Update these fields with `NOW()`
     * @return bool
     */
    public function insert($table, $array, array $nowFields = [])
    {
        if (!($table && is_array($array))) {
            return false;
        }
        if (empty($array) || empty($nowFields)) {
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
        foreach ($nowFields as $field) {
            $names[]  = $quoter->quoteIdentifier($field);
            $values[] = 'NOW()';
        }
        $names  = implode(',', $names);
        $values = implode(',', $values);

        $query = "INSERT INTO {$quoter->quoteIdentifier($table)} ($names) VALUES ($values)";
        $this->query($query);
        return true;
    }

    /**
     * Updates a row
     *
     * @deprecated use \Schoolbox\Mapper::save()
     *
     * @param $table
     * @param $identifier
     * @param $array
     *
     * @return bool
     */
    public function update($table, $identifier, $array)
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

            $identifier = array($identifier);
        }

        $set = '';
        foreach ($array as $key => $value) {
            if (!in_array($key, $identifier)) {
                if (!is_array($value)) {
                    $set .= '`' . $key . '`=' . $this->smartQuote($value) . ',';
                } elseif (isset($value['function'])) {
                    $set .= '`' . $key . '`=' . $value['function'] . '(' . $this->smartQuote($value['value']) . '),';
                }
            }
        }
        $set   = substr($set, 0, -1);
        $query = "UPDATE `$table` SET $set WHERE 1=1";

        foreach ($identifier as $ident) {
            $query .= " AND `$ident`= " . $this->smartQuote($array[$ident]);
        }

        $this->query($query);
        return true;
    }

    /**
     * @deprecated use \Schoolbox\Mapper::delete()
     *
     * @param $table
     * @param $identifier
     * @param $value
     *
     * @return bool
     */
    public function delete($table, $identifier, $value)
    {
        $query  = "DELETE FROM {$this->getQuoter()->quoteIdentifier($table)} WHERE 1 != 1 ";
        $params = [];
        if (is_array($value)) {
            foreach ($value as $item) {
                $query .= " OR {$this->getQuoter()->quoteIdentifier($identifier)} = ?";
                $params[] = $item;
            }
        } else {
            $query .= "OR {$this->getQuoter()->quoteIdentifier($identifier)}` = ?";
            $params[] = $value;
        }
        $this->query($query, $params);
        return true;
    }
}
