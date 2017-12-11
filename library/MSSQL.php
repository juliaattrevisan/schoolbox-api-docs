<?php
/**
 * This class is now deprecated. Use a DI injected \Alaress\Database\IConnector class instead.
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

use Alaress\Database\Connector\Base as Connector;
use Alaress\Database\Statement\IStatement;
use Alaress\Database\Statement\StoredProcedure\IStoredProcedure;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Database\Factory as DatabaseFactory;
use Schoolbox\IConfigLoader;

/**
 * @deprecated
 * Class MSSQL
 */
class MSSQL
{

    /** @var Connector $connector */
    protected $connector;

    /** @var IConfigLoader $config */
    protected $config;

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
     *
     * @deprecated
     * @var self
     */
    protected static $instance = null;

    /**
     * Gets static instance of this class (with default settings). Should be
     * alright as this never gets called
     *
     * @deprecated
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
     * @deprecated
     * @param bool $db
     * @param bool $optionalConfig
     */
    function __construct($db = false, $optionalConfig = false)
    {
        $this->connector = $this->connector = DatabaseFactory::getInstanceExternal();
        $this->config = ConfigMapper::getInstance();
    }

    /**
     * Ping function just attempts to reconnect to the database
     *
     * @deprecated
     */
    function isResponsive()
    {
        return $this->connector->connect(
            $this->config->get('external_db_host'),
            $this->config->get('external_db_db'),
            $this->config->get('external_db_user'),
            $this->config->get('external_db_passwd')
        );
    }

    /**
     * Executes a query, returns the statement
     *
     * @deprecated
     * @param string $sql_q
     * @return bool|IStatement
     */
    function query($sql_q)
    {
        $result = $this->connector->prepareQuery($sql_q);
        $result->execute();
        return $result;
    }

    /**
     * Runs a stored procedure
     *
     * @deprecated
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
     * @deprecated
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
     * @deprecated
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
     * @deprecated
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
     * @deprecated
     * @param string $sql_q
     * @return MssqlResultIterator
     */
    public function getIterator($sql_q)
    {
        return $this->connector->getQueryIterator($sql_q);
    }

    /**
     * Calls trimRow() on all rows from a rowset (returned by $this->fetchAll())
     * @see $this->trimRow()
     *
     * @deprecated
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
     * @deprecated
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
     * @deprecated
     * @param  mixed $fieldValue
     * @return mixed Trimmed value if $fieldValue was set, null otherwise.
     */
    public function trimField($fieldValue)
    {
        return isset($fieldValue) ? trim($fieldValue) : null;
    }

    /**
     * Escapes a value for safe usage in a query. The only escape needed is a
     * single apostrophe for two ("'" => "''")
     *
     * @deprecated
     * @param string $value The string to escape.
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
     * @deprecated
     * @param string $value The string to escape.
     * @return string
     */
    public function smartQuote($value)
    {
        return "'" . $this->quote($value) . "'";
    }

    /**
     * Return last error from connector
     *
     * @deprecated
     * @return string
     */
    public function getError()
    {
        return $this->connector->errorInfo();
    }
}
