<?php
/**
 * This class is now deprecated. Use a DI injected \Alaress\Database\IConnector class instead.
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Database\Connector\Base as Connector;
use Alaress\Database\Statement\IStatement;
use Alaress\Database\Statement\StoredProcedure\IStoredProcedure;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Database\Factory as DatabaseFactory;
use Schoolbox\Error\Mapper as ErrorMapper;
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
     * MSSQL constructor.
     *
     * @deprecated
     * @param bool $db
     * @param bool $optionalConfig
     * @throws \Alaress\Database\Exception
     * @throws \Schoolbox\Application\Exception\Exception
     */
    public function __construct($db = false, $optionalConfig = false)
    {
        $this->connector = DatabaseFactory::getInstanceExternal();
        $this->config    = ConfigLoader::getInstance();

        /* If the external db is disabled, this is null
         */
        if (null === $this->connector) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_DATABASE_UNAVAILABLE);
        }
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
     * @param array  $params
     * @return bool|IStatement
     */
    function query($sql_q, $params = [])
    {
        $result = $this->connector->prepareQuery($sql_q, $params);
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
     * @param null   $params
     * @return array|bool
     */
    function getRow($sql_q, $params = null)
    {
        $query = $this->connector->executeQuery($sql_q, $params);
        return $query->fetch();
    }


    /**
     * Gets the first value of the first result returned by the query. Returns an associative
     * array of row, false if none exists
     *
     * @deprecated
     * @param string $sql_q
     * @param null|array $params
     * @return mixed
     */
    function getOne($sql_q, $params = null)
    {
        $query = $this->connector->executeQuery($sql_q, $params);
        return $query->fetchColumn();
    }

    /**
     * Gets an 3 dimensional associative array of all rows returned by query,
     * returns false if no rows
     *
     * @deprecated
     * @param string $sql_q
     * @param array $params
     * @return array|bool
     */
    function getAll($sql_q, $params = null)
    {
        $query = $this->connector->executeQuery($sql_q, $params);
        return $query->fetchAll();
    }


    /**
     * Gets an iterator over a query.
     *
     * @deprecated
     * @param string $sql_q
     * @param null   $params
     * @return MssqlResultIterator
     */
    public function getIterator($sql_q, $params = null)
    {
        return $this->connector->getQueryIterator($sql_q, $params);
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
