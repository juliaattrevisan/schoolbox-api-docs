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
class MSSQL {

	/**
	 * This database's default encoding.
	 *
	 * Referenced when converting strings from database to UTF-8.
	 *
	 * @var string
	 */
	protected $encoding = 'ISO-8859-1';

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

	function query($sql_q)
	{
        if($this->dbf) {
            $this->result = mssql_query($sql_q, $this->dbf);

            if ($this->result == false) {
                if($this->debug)
                    print "<b>Query:</b> $sql_q<br />";
                else
                    error(21, 'Error in Synegetic query<br /><br /><p>'.mssql_get_last_message().'</p>' );

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
	 * @param bool   $convert_encoding True if all fields are to be converted
	 *                                 from DB's encoding to UTF-8
	 *
	 * @return array|bool
	 */
	function getRow($sql_q, $convert_encoding = false)
	{
		$result = $this->query($sql_q);
		$row = $this->getNextArray($result, $convert_encoding);
		return $row;
	}

	/**
	 * Gets the first value of the first result returned by the query
	 * Returns an associative array of row, false if none exists
	 *
 	 * @param string $sql_q
	 * @param bool   $convert_encoding True if all fields are to be converted
	 *                                 from DB's encoding to UTF-8
	 *
	 * @return array|bool
	 */
	function getOne($sql_q, $convert_encoding = false)
	{
		$result = $this->query($sql_q);
		$row = mssql_fetch_assoc($result);
		$one = $row[0];
		if ($convert_encoding) {
			$one = $this->convertEncoding($one);
		}
		return $one;
	}

	/**
	 * Gets an 3 dimensional associative array of all rows returned by query,
	 * returns false if no rows
	 *
	 * @param string $sql_q
	 * @param bool   $convert_encoding True if all fields are to be converted
	 *                                 from DB's encoding to UTF-8
	 * @return array|bool
	 */
	function getAll($sql_q, $convert_encoding = false)
	{
		$result = $this->query($sql_q);
		$table = array();
		$results = false;
		//loop through all records and insert into array table
		while($line = $this->getNextArray($result, $convert_encoding))
		{
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
	 * @param int  $result           The resource to use, or 0 to use
	 *                               $this->result
	 * @param bool $convert_encoding True if all fields are to be converted
	 *                               from DB's encoding to UTF-8
	 *
	 * @return array|bool
	 */
	function getNextArray($result = 0, $convert_encoding = false)
	{
		if($result)
				$return_array = mssql_fetch_assoc($result);
		else if($this->result)
			$return_array = mssql_fetch_assoc($this->result);
		else
			return false;

		if ($convert_encoding && is_array($return_array)) {
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

}
