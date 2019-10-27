<?php

/**
 * PDO Wrapper for IBM DB2
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Database\Wrapper\Db2;

use Alaress\Database\Exception;
use PDO as BasePDO;

class PDO extends BasePDO
{
    /** @var string|null $dsn */
    protected $dsn;

    /** @var string|null $username */
    protected $username;

    /** @var string|null $passwd */
    protected $passwd;

    /** @var array $options */
    protected $options;

    /** @var resource $connection */
    protected $connection = null;

    /** @var PDOStatement */
    protected $lastStatement = null;

    /**
     * This is an internal function to retrieve the database connection that makes sure its actually been set first
     *
     * @return resource
     */
    private function getConnectionWithCheck()
    {
        // Retrieve the database connection, but first do a sanity check to make sure its actually been set

        if ($this->connection == null) {
            throw new Exception("Unable to prepare DB2 statement. Database connection unavailable.");
        }

        return $this->connection;
    }

    /**
     * @inheritdoc
     */
    public function __construct($dsn, $username = null, $passwd = null, $options = [])
    {
        $this->dsn = $dsn;
        $this->username = $username;
        $this->passwd = $passwd;
        $this->options = $options;

        $this->connection = db2_connect($dsn, $username, $passwd, $options);
    }

    /**
     * @inheritdoc
     *
     * This is complaining about the declaration being incompatible with the interface. But the interface
     * on the vagrant PHP seems to be different from whatever storm is expecting
     *
     * Storm expects: public function execute($statement, $driver_options = [])
     *
     * @return bool|PDOStatement
     */
    public function prepare($statement, $options = null)
    {
        $this->lastStatement = new PDOStatement($this->getConnectionWithCheck(), $statement);
        return $this->lastStatement;
    }

    /**
     * @inheritdoc
     */
    public function beginTransaction()
    {
        return db2_autocommit($this->getConnectionWithCheck(), DB2_AUTOCOMMIT_OFF);
    }

    /**
     * @inheritdoc
     */
    public function commit()
    {
        return db2_commit($this->getConnectionWithCheck());
    }

    /**
     * @inheritdoc
     */
    public function rollBack()
    {
        return db2_rollback($this->getConnectionWithCheck());
    }

    /**
     * @inheritdoc
     */
    public function inTransaction()
    {
        return db2_autocommit($this->getConnectionWithCheck()) == 0;
    }

    /**
     * @inheritdoc
     */
    public function setAttribute($attribute, $value)
    {
        return db2_set_option($this->getConnectionWithCheck(), [$attribute => $value], 1);
    }

    /**
     * @inheritdoc
     */
    public function exec($statement)
    {
        $resource = db2_prepare($this->getConnectionWithCheck(), $statement, ['cursor' => DB2_SCROLLABLE]);

        if (!$resource) {
            return false;
        }

        // If the query execute successfully, return the number of affected rows

        $this->lastStatement = new PDOStatement($this->getConnectionWithCheck(), $resource, $statement);

        if (db2_execute($this->lastStatement->getStatement())) {
            return db2_num_rows($this->lastStatement->getStatement());
        }

        // If the query failed, return false

        return false;
    }

    /**
     * @inheritdoc
     * @return bool|PDOStatement
     */
    public function query($statement, $mode = BasePDO::ATTR_DEFAULT_FETCH_MODE, $arg3 = null, $ctorargs = [])
    {
        $resource = db2_prepare($this->getConnectionWithCheck(), $statement, ['cursor' => DB2_SCROLLABLE]);

        if (!$resource) {
            return false;
        }

        $this->lastStatement = new PDOStatement($this->getConnectionWithCheck(), $resource, $statement, $mode);

        if (db2_execute($this->lastStatement->getStatement())) {
            return $this->lastStatement;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function lastInsertId($name = null)
    {
        if ($name != null) {
            throw new Exception("The sequence object name parameter is not supported by DB2 wrapper.");
        }

        return db2_last_insert_id($this->getConnectionWithCheck());
    }

    /**
     * @inheritdoc
     */
    public function errorCode()
    {
        // Return the error code from the last statement executed if one had been executed via db2_prepare() or db2_exec()

        if ($this->lastStatement != null) {
            return db2_stmt_error($this->lastStatement->getStatement());
        }

        // If there was no statement executed, return the last connection error

        return db2_conn_error($this->getConnectionWithCheck());
    }

    /**
     * @inheritdoc
     */
    public function errorInfo()
    {
        // Return the error code from the last statement executed if one had been executed via db2_prepare() or db2_exec()

        if ($this->lastStatement != null) {
            return db2_stmt_errormsg($this->lastStatement->getStatement());
        }

        // If there was no statement executed, return the last connection error

        return db2_conn_errormsg($this->getConnectionWithCheck());
    }

    /**
     * @inheritdoc
     */
    public function getAttribute($attribute)
    {
        return db2_get_option($this->getConnectionWithCheck(), $attribute);
    }

    /**
     * @inheritdoc
     */
    public function quote($string, $parameter_type = BasePDO::PARAM_STR)
    {
        return db2_escape_string($string);
    }

    /**
     * Set underlying DB2 connection resource. You should not need to use this externally.
     *
     * @param $connection
     * @return self
     */
    public function setConnection($connection)
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * Return underlying DB2 connection resource. You should not need to use this externally.
     *
     * @return resource
     */
    public function getConnection()
    {
        return $this->connection;
    }
}
