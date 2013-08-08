<?php
/************************************************************
*                                                           *
* 		Copyright (c) 2008 Lektec Consulting				*
*                                                           *
*	This file provides abstraction of the  MSSQL database.	*
*	This used for Synergetic connections. 	 				*
*	It also includes error handling and a debug mode.		*
*                                                           *
************************************************************/

use Alaress\DebugBar\DataCollector\MssqlDatabaseCollector;

class MSSQL {

	/**
	 * This database's default encoding.
	 *
	 * Referenced when converting strings from database to UTF-8.
	 *
	 * @var string
	 */
	protected $encoding = 'ISO-8859-1';

	/**
	 * Is encoding converted or not for calls to getAll/Row/One?
	 *
	 * @var bool
	 */
	protected $convert_encoding = true;

	/**
	 * If encoding has been switched on or off for a single request, the
	 * previous value of $this->convert_encoding is here.
	 *
	 * @var bool
	 */
	protected $previous_encoding = null;

	/**
	 * @var MssqlDatabaseCollector
	 */
	protected $collector = null;

	/**
	 * Static instance of this class
	 *
	 * @var self
	 */
	protected static $instance = null;

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

	function connect() {
		if(!isset($this->dbf))
			$this->dbf = mssql_connect($this->host, $this->user, $this->passwd);

		if(!isset($this->dbf))
			error(22);

		if(!$this->changeDb($this->db))
			error(22);
	}

	function changeDB($db) {
		if(mssql_select_db($db, $this->dbf))
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

	function query($sql_q)
	{
		if($this->dbf) {
			if ($this->collector instanceof MssqlDatabaseCollector) {
				$this->result = $this->collector->query($sql_q);
			}
			else {
				$this->result = mssql_query($sql_q, $this->dbf);

				if ($this->result == false) {
					if($this->debug)
						print "<b>Query:</b> $sql_q<br />";
					else
						error(21, 'Error in Synegetic query<br /><br /><p>'.mssql_get_last_message().'</p>' );

				}
			}

			return $this->result;
		}
		else
			return false;
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
		$row = mssql_fetch_assoc($result);
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
	 * Converts $value's encoding to UTF-8, based on whatever this connection's
	 * encoding is (ISO-8859-1 if not specified).
	 *
	 * @param $value
	 *
	 * @return string
	 */
	protected function convertEncoding($value)
	{
		return mb_convert_encoding($value, 'UTF-8', $this->encoding);
	}


	/**
	 * Gets whether character set conversion is enabled.
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
	 * @param $convert
	 */
	public function setConvertEncoding($convert)
	{
		$this->convert_encoding = $convert;
	}


	/**
	 * Sets whether character set conversion was enabled for previous request.
	 *
	 * @param $previous_encoding
	 */
	protected function setPreviousEncoding($previous_encoding)
	{
		$this->previous_encoding = $previous_encoding;
	}

	/**
	 * Gets whether character set conversion was enabled for previous request.
	 *
	 * @return bool
	 */
	protected function getPreviousEncoding()
	{
		return $this->previous_encoding;
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
}
