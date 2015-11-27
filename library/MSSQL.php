<?php
/************************************************************
*                                                           *
* 		Copyright (c) 2013 Alaress Pty Ltd				*
*                                                           *
*	This file provides abstraction of the  MSSQL database.	*
*	This used for Synergetic connections. 	 				*
*	It also includes error handling and a debug mode.		*
*                                                           *
************************************************************/

use Alaress\DebugBar\DataCollector\MssqlDatabaseCollector;

class MSSQL {

	/**
	 * This used to store this database's default encoding, now does nothing.
	 *
	 * @deprecated use ini setting mssql.encoding instead
	 *
	 * @var string
	 */
	protected $encoding = 'ISO-8859-1';

	/**
	 * This used to control whether encoding was converted or not for calls to
	 * getAll/Row/One, now does nothing.
	 *
	 * @deprecated use ini setting mssql.encoding instead
	 *
	 * @var bool
	 */
	protected $convert_encoding = false;

	/**
	 * @var MssqlDatabaseCollector
	 */
	protected $collector = null;

    /**
     * The database connection resource
     * @var resource
     */
    protected $dbf = null;

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
     * Most recent result resource
     * @var resource
     */
    protected $result = null;

    /**
	 * Static instance of this class
	 *
	 * @var self
	 */
	protected static $instance = null;

    /**
     * @param bool $db
     * @param bool $optionalConfig
     */
    function MSSQL($db = false, $optionalConfig = false) {

		/* Blasphemy (heresy unavoidable)
		 * Hijack these settings for alternative config
		 */
		if (is_array($optionalConfig)) {
			$this->host = $optionalConfig['host'];
			$this->user = $optionalConfig['user'];
			$this->passwd = $optionalConfig['passwd'];
			$this->db = $optionalConfig['db'];

			$this->connect();
			$encoding = $optionalConfig['encoding'];
		}
		else {
			$this->host = Cfg::get('external_db_host');
			$this->user = Cfg::get('external_db_user');
			$this->passwd = Cfg::get('external_db_passwd');
			$this->debug = 0;
			$this->db = $db?$db:Cfg::get('external_db_db');

			if(Cfg::get('external_enable') == 1) {
				$this->connect();
			}
			$encoding = Cfg::get('external_db_encoding');
		}
		if (!empty($encoding)) {
			$this->encoding = $encoding;
		}
	}

	/**
	 * Gets static instance of this class (with default settings). Should be
     * alright as this never gets called
	 *
	 * @return self
	 */
	public static function getInstance() {
		if (!self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

    /**
     * Connect to database
     * @throws Schoolbox\External\Exception
     */
    function connect() {
		if (!isset($this->dbf)) {
			// mssql.charset not already set? set it to UTF-8 here.
			if (strlen(ini_get('mssql.charset')) === 0) {
				ini_set('mssql.charset', 'UTF-8');
			}
			$this->dbf = @mssql_connect($this->host, $this->user, $this->passwd);
		}

		if ($this->dbf == false) {
			throw new \Schoolbox\External\Exception("External database is offline.");
		}

		if (!$this->changeDb($this->db)) {
			throw new \Schoolbox\External\Exception("External database is online, but unable to connect.");
		}

        /* Set Default NULL ON, due to stored procs requiring this */
        mssql_query('SET ANSI_NULL_DFLT_ON ON',$this->dbf);
        mssql_query('SET CONCAT_NULL_YIELDS_NULL ON', $this->dbf);
	}

    /**
     * Change to a different DB on server
     * @param string $db
     * @return bool
     */
    function changeDB($db) {
		if(@mssql_select_db($db, $this->dbf))
		{
			$this->db = $db;
			return true;

		}
		else
			return false;
	}

	/**
	 * Installs the PHP debug bar collector into this object.
	 */
	public function installCollector() {
		if (!($this->collector instanceof MssqlDatabaseCollector)) {
			$this->collector = new MssqlDatabaseCollector($this->dbf);
		}
	}

	/**
	 * @return MssqlDatabaseCollector
	 */
	public function getCollector() {
		return $this->collector;
	}

    /**
     * @param string $sql_q
     * @return bool|resource
     */
    function query($sql_q)
	{
		// SB-6907: Cathedral's SQL server won't accept long query strings, so
		// strip any leading space from each line of the query.
		// (I can't guarantee that this won't change the meaning of any query,
		//  but 1. we never insert into any MSSQL DB, and 2. we never query on
		//  any value with a line break, so this should be fine.
		$sql_q = preg_replace('/^\\s+/m', '', $sql_q);
		if($this->dbf) {
			if ($this->collector instanceof MssqlDatabaseCollector) {
				$this->result = $this->collector->query($sql_q);
			}
			else {
				$this->result = mssql_query($sql_q, $this->dbf);

				if ($this->result == false) {
					if ($this->debug) {
						trigger_error(sprintf('MSSQL Query: "%s" triggered error: "%s"', $sql_q, mssql_get_last_message()), E_USER_WARNING);
					}
					else {
						error(21, 'Error in Microsoft SQL query<br /><br /><p>'.mssql_get_last_message().'</p>' );
					}
				}
			}

			return $this->result;
		}
		else
			return false;
	}

	/**
	 * Runs a stored procedure
	 *
	 * @param string  $name   The name of the stored procedure.
	 * @param array[] $params A list of parameters to bind {@see mssql_bind}
	 *                        Each array in this list should contain parameters
	 *                        to mssql_bind in order, minus the first (the stored
	 *                        procedure resource is created by this method)
	 *
	 * @return array success: Did the procedure run successfully?
	 *               output:  output vars from the procedure
	 *               result:  if the procedure returned a result set, the results
	 *                        from the procedure (untested)
	 */
	public function executeProcedure($name, $params)
	{
		$bound_outputs = array();
		$proc          = $this->buildProcedure($name, $params, $bound_outputs);

		// Run procedure, fetching the result set if there is one
		$success = true;
		$return  = array();
		try {
			$result  = mssql_execute($proc);
			$message = mssql_get_last_message();

			if (is_resource($result)) {
				while ($row = mssql_fetch_assoc($result)) {
                    $return[] = $row;
				}
                /* This fixes issue where output params were not available after resultset was returned */
                mssql_next_result($result);

            }
			elseif ($result === false) {
				// Did the procedure error out? Return nothing instead.
				$success       = false;
				$bound_outputs = array();
			}
		}
		catch (ErrorException $e) {
			$success       = false;
			$bound_outputs = array();
			$message       = $e->getMessage();
		}
		mssql_free_statement($proc);

        return array(
			'success' => $success,
			'output'  => $bound_outputs,
			'result'  => $return,
			'message' => $message,
		);
	}

	/**
	 * Gets the first result returned by the query
	 * Returns an associative array of row, false if none exists
	 *
	 * @param string $sql_q
	 *
	 * @return array|bool
	 */
	function getRow($sql_q)
	{
		$result = $this->query($sql_q);
		return $this->getNextArray($result);
	}

	/**
	 * Gets the first value of the first result returned by the query
	 * Returns an associative array of row, false if none exists
	 *
	 * @param string $sql_q
	 *
	 * @return array|bool
	 */
	function getOne($sql_q)
	{
		$result = $this->query($sql_q);
		$row = array_values(mssql_fetch_assoc($result));
		$one = $row[0];
		if ($this->getConvertEncoding()) {
			$one = $this->convertEncoding($one);
		}
		return $one;
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
		$result = $this->query($sql_q);
		$table = array();
		$results = false;
		//loop through all records and insert into array table
		while ($line = $this->getNextArray($result)) {
			$table[] = $line;
			$results = true;
		}
		if($results)
			return $table;
		else
			return false;
	}

	/**
	 * Gets an iterator over a query.
	 *
	 * @param string $sql_q
	 * @return MssqlResultIterator
	 */
	public function getIterator($sql_q)
	{
		return new MssqlResultIterator($this->query($sql_q));
	}

	/**
	 * Gets an iterator over a stored procedure.
	 *
	 * This currently only works with stored procs that return a result set
	 * directly - any result text or output variables will be ignored.
	 *
	 * @see $this->executeProcedure()
	 *
	 * @return MssqlResultIterator
	 */
	public function getProcedureIterator($name, $params)
	{
		// Bound outputs will be ignored
		$bound_outputs = [];
		$proc          = $this->buildProcedure($name, $params, $bound_outputs);
		return new MssqlResultIterator(mssql_execute($proc));
	}

	/**
	 * Returns the next result from a query result resource
	 * Can be optionally passed a result resource otherwise it uses current result resource
	 *
	 * @param int  $result The resource to use, or 0 to use $this->result
	 *
	 * @return array|bool
	 */
	function getNextArray($result = 0)
	{
		if($result)
				$return_array = mssql_fetch_assoc($result);
		else if($this->result)
			$return_array = mssql_fetch_assoc($this->result);
		else
			return false;

		if ($this->getConvertEncoding() && is_array($return_array)) {
			$return_array = array_map(array($this, 'convertEncoding'), $return_array);
		}

		return $return_array;
	}

	/**
	 * Calls trimRow() on all rows from a rowset (returned by $this->fetchAll())
	 * @see $this->trimRow()
	 *
	 * @param  array $rowset
	 * @return array
	 */
	public function trimAll($rowset) {
        if(is_array($rowset)) {
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
	public function trimRow($row) {
        if(is_array($row)) {
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
	public function trimField($fieldValue) {
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
		if($result)
			$return_count = mssql_num_rows($result);
		else if($this->result)
			$return_count = mssql_num_rows($this->result);
		else
			return false;
		return $return_count;
	}

	/**
	 * Used to converts $value's encoding to UTF-8, based on whatever this
	 * connection's encoding was (ISO-8859-1 if not specified).
	 *
	 * Now does nothing, because mssql.encoding handles charset conversion
	 * properly and transparently.
	 *
	 * @deprecated use ini setting mssql.encoding instead
	 *
	 * @param $value
	 *
	 * @return string
	 */
	protected function convertEncoding($value)
	{
		return $value;
//		return mb_convert_encoding($value, 'UTF-8', $this->encoding);
	}


	/**
	 * Gets whether character set conversion is enabled.
	 *
	 * @deprecated use ini setting mssql.encoding instead
	 *
	 * @return bool
	 */
	public function getConvertEncoding()
	{
		return $this->convert_encoding;
	}


	/**
	 * Sets whether character set conversion is enabled.
	 *
	 * @deprecated use ini setting mssql.encoding instead
	 *
	 * @param $convert
	 */
	public function setConvertEncoding($convert)
	{
		$this->convert_encoding = $convert;
	}

	/**
	 * Escapes a value for safe usage in a query. The only escape needed is a
	 * single apostrophe for two ("'" => "''")
	 *
	 * @param string $value The string to escape.
	 *
	 * @return string
	 */
	public function quote($value) {
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
		return "'".$this->quote($value)."'";
	}

	/**
	 * Check whether a db resource was spawned
	 *
	 * @return bool
	 */
	public function isConnected() {
		if ($this->dbf !== null) {
			return true;
		}

		return false;
	}

	/**
	 * @param string $name
	 * @param array  $params
	 * @param array  &$bound_outputs
	 *
	 * @return resource
	 */
	protected function buildProcedure($name, $params, &$bound_outputs)
	{
		$proc         = mssql_init($name, $this->dbf);
		$bound_inputs = array();

		// Bind each parameter to the procedure
		foreach ($params as $param) {
			list($param_name, $var, $type, $is_output, $is_null, $maxlen) = $param;
			if (!$is_output) {
				$is_output = false;
			}
			if (!$is_null) {
				$is_null = false;
			}
			if (!$maxlen) {
				$maxlen = -1;
			}
			if ($is_output) {
				mssql_bind($proc, $param_name, $bound_outputs[$param_name], $type, true, $is_null, $maxlen);
			} else {
				$bound_inputs[$param_name] = $var;
				mssql_bind($proc, $param_name, $bound_inputs[$param_name], $type, false, $is_null, $maxlen);
			}
		}
		return $proc;
	}
}
