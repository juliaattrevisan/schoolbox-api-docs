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

	function MSSQL($db = MSSQL_db) {
		$this->host = MSSQL_host;
		$this->user = MSSQL_user;
		$this->passwd = MSSQL_passwd;
		$this->debug = $debug;
		$this->db = $db;
		$this->connect();
	}

	function connect() {
		if(!isset($this->dbf))
			$this->dbf = mssql_connect($this->host, $this->user, $this->passwd);
		
		if(!isset($this->dbf))
			error(65);

		if(!$this->changeDb($this->db))
			error(65);	
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
		$this->result = mssql_query($sql_q, $this->dbf);
		
		if ($this->result == FALSE) {
			if($this->debug)
				print "<b>Query:</b> $sql_q<br />";
			else
				error(21, 'Error in Synegetic query<br /><br />'.$sql_q );
		}
		
		return $this->result;
	}

	/* Gets the first result returned by the query */
	/* Returns an associative array of row, false if none exists */
	function getRow($sql_q)
	{
		$result = $this->query($sql_q);
		$row = $this->getNextArray($result);
		return $row;
	}

	/* Gets the first value of the first result returned by the query */
	/* Returns an associative array of row, false if none exists */
	function getOne($sql_q)
	{
		$result = $this->query($sql_q);
		$row = mssql_fetch_assoc($result);
		return $row[0];
	}
	
	/* Gets an 3 dimensional associative array of all rows returned by query, */
	/*	returns false if no rows */
	function getAll($sql_q)
	{
		$result = $this->query($sql_q);
		$table = array();
		$results = false;
		//loop through all records and insert into array table
		while($line = $this->getNextArray($result))
		{
			$table[] = $line;
			$results = true;
		}
		if($results)
			return $table;
		else
			return false;
	}
	
	/* Returns the next result from a query result resource */
	/* Can be optionally passed a result resource otherwise it uses current result resource */
	function getNextArray($result = 0)
	{
		if($result)
				$return_array = mssql_fetch_assoc($result);
		else if($this->result)
			$return_array = mssql_fetch_assoc($this->result);
		else
			return false;
	
		return $return_array;
	}
	
	/* Returns the number of rows returned by the query */
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
	
}
?>