<?php
/************************************************************
*                                                           *
* 		Copyright (c) 2003-2004 Lektec Consulting			*
*                                                           *
*	This file provides abstraction of the database.			*
*	This implements MySQL connections.  All standard 		*
*	functions are accessible plus some incapsulated 		*
*	functions like getSingleRow and getAllRows.				*
*	It also includes error handling and a debug mode.		*		    
*                                                           *
************************************************************/

class dbManager
{
    /**
     * MySQLi connection, should not be public but has a history of being
     * accessed outside of its class
     *
     * @var mysqli
     */
    public $dbf;

    /**
     * MySQLi result, connection, should not be public but has a history of being
     * accessed outside of its class
     *
     * @var mysqli_result
     */
    public $result;

/* Constuctor sets up variables and connects to database */
function dbManager($db = SQL_db, $debug = 0)
{
	$this->host = SQL_host;
	$this->user = SQL_user;
	$this->passwd = SQL_passwd;
	$this->debug = $debug;
	$this->db = $db;
	$this->connect();
}

/* Estabilishes a connection to the database */
function connect()
{
	if(!isset($this->dbf))
		$this->dbf = mysqli_connect($this->host, $this->user, $this->passwd);
	
	if(!isset($this->dbf)){
		print "Unable to connect ".$this->user."@".$this->host." using ".$this->db; 
		exit;
	}	
	if(!$this->changeDb($this->db))
		print "Unable to access ".$this->db.", check the permissions";
}

/* Closes the connection to the database */
function close()
{
	return mysqli_close ($this->dbf);
}

/* Changes the current selected database */
function changeDb($db)
{
	if(mysqli_select_db($this->dbf, $db))
	{
		$this->db = $db;
		return true;
	}
	else
		return false;
}

/* Executes query on the database and handles any errors */
/* Returns the result resource for the query, also saves the result resource as a object var */
function query($sql_q)
{
	$this->result = mysqli_query($this->dbf, $sql_q) or $mysqli_eval_error = mysqli_error($this->dbf);
	
	if (isset($mysqli_eval_error)) {
		if($this->debug)
		{
			print "<b>Query:</b> $sql_q<br />";
			print "<b>Error:</b> $mysqli_eval_error";
		}
		else
			error(4, $mysqli_eval_error);
	}
	
	return $this->result;
}

/* Returns the current result resource */
function getResult()
{
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
	$row = mysqli_fetch_row($result);
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
	        $return_array = mysqli_fetch_assoc($result);
	else if($this->result)
		$return_array = mysqli_fetch_assoc($this->result);
	else
		return false;

	return $return_array;
}


/* Returns the number of rows returned by the query */
function countRows($result = 0)
{
	if($result)
		$return_count = mysqli_num_rows($result);
	else if($this->result)
		$return_count = mysqli_num_rows($this->result);
	else
		return false;
	return $return_count;
}

/* Inserts an array into table (does all proper security checks to ensure safe) */
function insert($table, $array)
{
	if(!($table && is_array($array)))
		return FALSE;

	$names = '';
	$values = '';
	
	foreach($array as $key => $value)
	{
		$names .= '`'.$key.'`' . ',';
		if (!is_array($value) ) {
			$values .= $this->smartQuote($value) . ',';
		} else if (isset($value['function'])) {
			$values .= $value['function']. '('. $this->smartQuote($value['value']). '),';
		}
	}
	$names = substr($names, 0, -1);
	$values = substr($values, 0, -1);
	
	$query = "INSERT INTO `$table` ($names) VALUES ($values)";
	return $this->query($query);
}

/* Inserts a multidimension array into table (does all proper security checks to ensure safe) */
function insertMultiRow($table, $array, $names=false)
{
	if(!($table && is_array($array)))
		return FALSE;
	
	//	try to get the names from the first row if they are not sent seperatly
	if (!$names) {
		$names = array_keys($array[0]);
	}
	$cols = '`'. implode('`,`', $names). '`';
	$rows = array();
	foreach ($array as $row) {
		
		$values = '(';
		
		foreach($row as $key => $value)
		{
			if (!is_array($value)) {
				$values .= $this->smartQuote($value) . ',';
			} else if (isset($value['function'])) {
				$values .= $value['function']. '('. $this->smartQuote($value['value']). '),';
			}
		}
		$values = substr($values, 0, -1). ')';
		$rows[] = $values;
	}
	
	$query = "INSERT INTO `$table` ($cols) VALUES ";
	$query .= implode(',', $rows);
	
	return $this->query($query);
}

/* Updates a row */
function update($table, $identifier, $array)
{
	if(!$table) 
		return FALSE;
		
	if(is_array($identifier)) {
		foreach($identifier as $ident){
			if(!$array[$ident] )
				return FALSE;
		}
	}
	else {
	 	if(!$array[$identifier] )
			return FALSE;

		$identifier = array($identifier);
	}
	
	$set = '';
	foreach($array as $key => $value)
	{
	
		if(!in_array($key, $identifier)) {
			if (!is_array($value)) {
				$set .= '`'.$key.'`='.$this->smartQuote($value).',';
			} else if (isset($value['function'])) {
				$set .= '`'.$key.'`='. $value['function']. '('.$this->smartQuote($value['value']).'),';
			}
		}
	}
	$set = substr($set, 0, -1);
	$query = "UPDATE `$table` SET $set WHERE 1=1";
	
	foreach($identifier as $ident) {
		$query .= " AND `$ident`= ".$this->smartQuote($array[$ident]); 
	}

	return $this->query($query);
}

function delete($table, $identifier, $value)
{
	$query = "DELETE FROM `$table` WHERE 1 != 1 ";
	if (is_array($value)) {
		foreach ($value as $item) {
			$query .= " OR `$identifier` =  ".$this->smartQuote($value);
		}
	} else {
		$query .= "OR `$identifier` =  ".$this->smartQuote($value);
	}
	return $this->query($query);
}


/* Returns the id of the last record inserted */
function insertID()
{
	$return_id = mysqli_insert_id($this->dbf);
	return $return_id;
}	

/* Returns the number of rows affected by the last update */
function affectedRows()
{
	$return_count = mysqli_affected_rows($this->dbf);
	return $return_count;
}

/* Free the result resource */
function endQuery($result = 0)
{
	if ($result)
		mysqli_free_result($result);
	else if($this->result)
		mysqli_free_result($this->result);
	else
		return false;
}

/* PRIVATE FUNCTIONS */

// Quote variable to make safe for sql use
function smartQuote($value)
{
   // Stripslashes
   if (get_magic_quotes_gpc()) {
       $value = stripslashes($value);
   }
   
   $mysqlFuncs = array('NULL', 
   					   'NOW()',
					   );
   
   
   // Quote if not integer or special
   if (!((is_numeric($value) && $value[0] != 0) || in_array($value, $mysqlFuncs))) {
       $value = "'" . mysqli_real_escape_string($this->dbf, $value) . "'";
   }
   return $value;
}

/* DEPRECEATED FUNCTIONS */

function dbConnect() {
	$this->connect();
}

function dbClose() {
	return $this->close();
}

function dbChangeDb($db) {
	return $this->changeDb($db);
}

function dbDoQuery($sql_q) {
	return $this->query($sql_q);
}

function dbGetResult() {
	return $this->getResult();
}

function dbGetSingleRow($sql_q) {
	return $this->getRow($sql_q);
}

function dbGetAllRows($sql_q) {
		return $this->getAll($sql_q);
}

function dbGetNextArray($result = 0) {
	return $this->getNextArray($result);
}

function dbCountRows($result = 0) {
	return $this->countRows($result);
}

function dbInsertRow($table, $array) {
	return $this->insert($table, $array);
}

function dbInsertMultiRow($table, $array, $names=false) {
	return $this->insertMultiRow($table, $array, $names);
}

function dbUpdateRow($table, $identifier, $array) {
	return $this->update($table, $identifier, $array);
}

function dbDeleteRow($table, $identifier, $value) {
	return $this->delete($table, $identifier, $value);
}

function dbInsertId() {
	return $this->insertID();
}	

function dbCountRowsAffected() {
	return $this->affectedRows();
}

function dbEndQuery($result = 0) {
	return $this->endQuery($result);
}

function dbSmartQuote($value) {
   return $this->smartQuote($value);
}

}
?>
