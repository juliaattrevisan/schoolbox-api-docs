<?
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

/* Constuctor sets up variables and connects to database */
function dbManager($db = SQL_db, $debug = 0)
{
	$this->host = SQL_host;
	$this->user = SQL_user;
	$this->passwd = SQL_passwd;
	$this->debug = $debug;
	$this->db = $db;
	
}

/* Estabilishes a connection to the database */
function dbConnect()
{
	if(!isset($this->dbf)) {
		$this->dbf = mysql_connect($this->host, $this->user, $this->passwd);
		$this->dbChangeDb($this->db);
	}
	if(!$this->dbf){
		print "Unable to connect ".$this->user."@".$this->host." using ".$this->db; 
		exit;
	}	
}

/* Closes the connection to the database */
function dbClose()
{
	return mysql_close ($this->dbf);
}

/* Changes the current selected database */
function dbChangeDb($db)
{
	if(mysql_select_db($db, $this->dbf))
	{
		$this->db = $db;
		return true;
	}
	else
		return false;
}

/* Executes query on the database and handles any errors */
/* Returns the result resource for the query, also saves the result resource as a object var */
function dbDoQuery($sql_q)
{
	$this->dbConnect();
	$this->result = mysql_query($sql_q, $this->dbf) or $mysql_eval_error = mysql_error();
	if (isset($mysql_eval_error)) {
		if($this->debug)
		{
			print "<b>Query:</b> $sql_q<br />";
			print "<b>Error:</b> $mysql_eval_error";
		}
		else
			error(6, $mysql_eval_error);
	}
	
	return $this->result;
}

/* Returns the current result resource */
function dbGetResult()
{
	return $this->result;
}

/* Gets the first result returned by the query */
/* Returns an associative array of row, false if none exists */
function dbGetSingleRow($sql_q)
{
	$result = $this->dbDoQuery($sql_q);
	$row = $this->dbGetNextArray($result);
	return $row;
}

/* Gets an 3 dimensional associative array of all rows returned by query, */
/*	returns false if no rows */
function dbGetAllRows($sql_q)
{
	$result = $this->dbDoQuery($sql_q);
	$table = array();
	$results = false;
	//loop through all records and insert into array table
	while($line = $this->dbGetNextArray($result))
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
function dbGetNextArray($result = 0)
{
	if($result)
	        $return_array = mysql_fetch_assoc($result);
	else if($this->result)
		$return_array = mysql_fetch_assoc($this->result);
	else
		return false;

	return $return_array;
}


/* Returns the number of rows returned by the query */
function dbCountRows($result = 0)
{
	if($result)
		$return_count = mysql_num_rows($result);
	else if($this->result)
		$return_count = mysql_num_rows($this->result);
	else
		return false;
	return $return_count;
}

/* Inserts an array into table (does all proper security checks to ensure safe) */
function dbInsertRow($table, $array)
{
	if(!($table && is_array($array)))
		return FALSE;

	$names = '';
	$values = '';
	
	foreach($array as $key => $value)
	{
		$names .= '`'.$key.'`' . ',';
		if (!is_array($value)) {
			$values .= $this->dbSmartQuote($value) . ',';
		} else if (isset($value['function'])) {
			$values .= $value['function']. '('. $this->dbSmartQuote($value['value']). '),';
		}
	}
	$names = substr($names, 0, -1);
	$values = substr($values, 0, -1);
	
	$query = "INSERT INTO `$table` ($names) VALUES ($values)";
	return $this->dbDoQuery($query);
}

/* Inserts a multidimension array into table (does all proper security checks to ensure safe) */
function dbInsertMultiRow($table, $array, $names=false)
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
				$values .= $this->dbSmartQuote($value) . ',';
			} else if (isset($value['function'])) {
				$values .= $value['function']. '('. $this->dbSmartQuote($value['value']). '),';
			}
		}
		$values = substr($values, 0, -1). ')';
		$rows[] = $values;
	}
	
	$query = "INSERT INTO `$table` ($cols) VALUES ";
	$query .= implode(',', $rows);
	
	return $this->dbDoQuery($query);
}

/* Updates a row */
function dbUpdateRow($table, $identifier, $array)
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
				$set .= '`'.$key.'`='.$this->dbSmartQuote($value).',';
			} else if (isset($value['function'])) {
				$set .= '`'.$key.'`='. $value['function']. '('.$this->dbSmartQuote($value['value']).'),';
			}
		}
	}
	$set = substr($set, 0, -1);
	$query = "UPDATE `$table` SET $set WHERE 1=1";
	
	foreach($identifier as $ident) {
		$query .= " AND `$ident`= ".$this->dbSmartQuote($array[$ident]); 
	}
	return $this->dbDoQuery($query);
}

function dbDeleteRow($table, $identifier, $value)
{
	$query = "DELETE FROM `$table` WHERE 1 != 1 ";
	if (is_array($value)) {
		foreach ($value as $item) {
			$query .= " OR `$identifier` =  ".$this->dbSmartQuote($value);
		}
	} else {
		$query .= "OR `$identifier` =  ".$this->dbSmartQuote($value);
	}
	return $this->dbDoQuery($query);
}


/* Returns the id of the last record inserted */
function dbInsertId()
{
	$return_id = mysql_insert_id($this->dbf);
	return $return_id;
}	

/* Returns the number of rows affected by the last update */
function dbCountRowsAffected()
{
	$return_count = mysql_affected_rows($this->dbf);
	return $return_count;
}

/* Free the result resource */
function dbEndQuery($result = 0)
{
	if ($result)
		mysql_free_result($result);
	else if($this->result)
		mysql_free_result($this->result);
	else
		return false;
}

/* PRIVATE FUNCTIONS */

// Quote variable to make safe for sql use
function dbSmartQuote($value)
{
   // Stripslashes
   if (get_magic_quotes_gpc()) {
       $value = stripslashes($value);
   }
   // Quote if not integer or special
   if (!(is_numeric($value) || $value == 'NULL' ||  $value == 'NOW()')) {
       $value = "'" . mysql_real_escape_string($value) . "'";
   }
   return $value;
}
}
?>
