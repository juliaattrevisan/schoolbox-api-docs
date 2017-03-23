<?php
/************************************************************
 *                                                           *
 *        Copyright (c) 2013 Alaress Pty Ltd                *
 *                                                           *
 *    This file provides abstraction of the  MSSQL database.    *
 *    This used for Synergetic connections.                    *
 *    It also includes error handling and a debug mode.        *
 *                                                           *
 ************************************************************/

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Factory as DatabaseFactory;
use Alaress\Database\Statement\IStatement;
use Alaress\Database\Statement\StoredProcedure\IStoredProcedure;

class MSSQL
{

    /** @var IConnector */
    protected $connector;

    /**
     * Database host address
     * @var string
     */
    protected $host = null;

    /**
     * Database user
     * @var string
     */
    protected $user = null;

    /**
     * Database password
     * @var string
     */
    protected $passwd = null;

    /**
     * Database name
     * @var string
     */
    protected $db = null;

    /**
     * Static instance of this class
     * @var self
     */
    protected static $instance = null;

    /**
     * Most recent statement executed
     * @var IStatement
     */
    protected $result = null;

    /**
     * Gets static instance of this class (with default settings). Should be
     * alright as this never gets called
     *
     * @return self
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @param bool $db
     * @param bool $optionalConfig
     */
    function __construct($db = false, $optionalConfig = false)
    {
        $this->connector = DatabaseFactory::CreateSqlsrv();

        if (is_array($optionalConfig)) {
            $this->host = $optionalConfig['host'];
            $this->user = $optionalConfig['user'];
            $this->passwd = $optionalConfig['passwd'];
            $this->db = $optionalConfig['db'];
        } else {
            $this->host = Cfg::get('external_db_host');
            $this->user = Cfg::get('external_db_user');
            $this->passwd = Cfg::get('external_db_passwd');
            $this->db = $db ? $db : Cfg::get('external_db_db');
        }

        $this->connect();
    }

    /**
     * Connect to database
     *
     * @throws Schoolbox\External\Exception
     */
    function connect()
    {
        return $this->connector->connect(
            $this->host,
            $this->db,
            $this->user,
            $this->passwd
        );
    }

    /**
     * @param string $sql_q
     * @return bool|resource
     */
    function query($sql_q)
    {
        $this->result = $this->connector->prepareQuery($sql_q);
        $this->result->execute();
        return $this->result;
    }

    /**
     * Runs a stored procedure
     *
     * @param string $name The name of the stored procedure.
     * @return IStoredProcedure
     */
    public function prepareProcedure($name)
    {
        return $this->connector->prepareStoredProcedure($name);
    }

    /**
     * Gets the first result returned by the query. Returns an associative array of row,
     * false if none exists
     *
     * @param string $sql_q
     * @return array|bool
     */
    function getRow($sql_q)
    {
        $query = $this->connector->executeQuery($sql_q);
        return $query->fetch();
    }

    /**
     * Gets the first value of the first result returned by the query. Returns an associative
     * array of row, false if none exists
     *
     * @param string $sql_q
     * @return array|bool
     */
    function getOne($sql_q)
    {
        $query = $this->connector->executeQuery($sql_q);
        return $query->fetchColumn();
    }

    /**
     * Gets an 3 dimensional associative array of all rows returned by query,
     * returns false if no rows
     *
     * @deprecated Use getIterator() instead if possible.
     *
     * @param string $sql_q
     * @return array|bool
     */
    function getAll($sql_q)
    {
        $query = $this->connector->executeQuery($sql_q);
        return $query->fetchAll();
    }

    /**
     * Gets an iterator over a query.
     *
     * @param string $sql_q
     * @return MssqlResultIterator
     */
    public function getIterator($sql_q)
    {
        return $this->connector->executeQueryIterator($sql_q);
    }

    /**
     * Returns the next result from a query result resource
     * Can be optionally passed a result resource otherwise it uses current result resource
     *
     * @param int $result The resource to use, or 0 to use $this->result
     * @return array|bool
     */
    function getNextArray($result = 0)
    {
        if ($result instanceof IStatement) {
            return $result->fetch();
        } elseif ($result == 0 && $this->result instanceof IStatement) {
            return $this->result->fetch();
        }

        return false;
    }

    /**
     * Calls trimRow() on all rows from a rowset (returned by $this->fetchAll())
     * @see $this->trimRow()
     *
     * @param  array $rowset
     * @return array
     */
    public function trimAll($rowset)
    {
        if (is_array($rowset)) {
            return array_map(array($this, 'trimRow'), $rowset);
        }
        return $rowset;
    }

    /**
     * Trims all field values in a single row (returned by $this->fetchRow())
     *
     * Needed by TASS because most of its fields are CHAR instead of VARCHAR.
     *
     * @param  array $row
     * @return array
     */
    public function trimRow($row)
    {
        if (is_array($row)) {
            return array_map(array($this, 'trimField'), $row);
        }
        return $row;
    }

    /**
     * Trims a single field value.
     *
     * @param  mixed $fieldValue
     * @return mixed Trimmed value if $fieldValue was set, null otherwise.
     */
    public function trimField($fieldValue)
    {
        return isset($fieldValue) ? trim($fieldValue) : null;
    }

    /**
     * Returns the number of rows returned by the query
     *
     * @param int $result
     *
     * @return bool|int
     */
    function countRows($result = 0)
    {
        if ($result instanceof IStatement)
            return $result->rowCount();
        else if ($this->result instanceof IStatement)
            return $this->result->rowCount();

        return false;
    }

    /**
     * Escapes a value for safe usage in a query. The only escape needed is a
     * single apostrophe for two ("'" => "''")
     *
     * @param string $value The string to escape.
     *
     * @return string
     */
    public function quote($value)
    {
        return str_replace("'", "''", $value);
    }

    /**
     * Escapes a value for safe usage in a query, and wraps in quotes so that
     * it can be interposed directly into a string.
     *
     * @param string $value The string to escape.
     *
     * @return string
     */
    public function smartQuote($value)
    {
        return "'" . $this->quote($value) . "'";
    }

    /**
     * Check whether a db resource was spawned
     *
     * @return bool
     */
    public function isConnected()
    {
        return $this->connector !== null;
    }

    /**
     * Return last error from connector
     *
     * @return string
     */
    public function getError()
    {
        return $this->connector->errorInfo();
    }
}
