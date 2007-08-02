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
function dbManager($db = DB_NAME, $debug = 0)
{
	$this->host = DB_HOST;
	$this->user = DB_USER;
	$this->passwd = DB_PASSWD;
	$this->debug = $debug;
	$this->db = $db;
	$this->dbConnect();
}

/* Estabilishes a connection to the database */
function dbConnect()
{
	if(!$this->dbf)
		$this->dbf = mysql_connect($this->host, $this->user, $this->passwd);
	
	if(!$this->dbf){
		print "Unable to connect ".$this->user."@".$this->host." using ".$this->db; 
	}	
	$this->dbChangeDb($this->db);
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
	$this->result = mysql_query($sql_q, $this->dbf) or $mysql_eval_error = mysql_error();
	if ($mysql_eval_error) {
		print "<b>Query:</b> $sql_q<br />";
		print "<b>Error:</b> $mysql_eval_error";
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
	//loop through all records and insert into array table
	while($line = $this->dbGetNextArray($result))
	{
		$table[] = $line;
	}
	return $table;
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
function dbInsertArray($table, $array)
{
	if(!($table || is_array($array)))
		return FALSE;

	$names = '';
	$values = '';
	
	foreach($array as $key => $value)
	{
		$names .= '`'.$key.'`' . ',';
		$values .= $this->dbSmartQuote($value) . ',';
	}
	$query = "INSERT INTO `$table` ($names) VALUES ($values)";
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
   // Quote if not integer
   if (!is_numeric($value)) {
       $value = "'" . mysql_real_escape_string($value) . "'";
   }
   return $value;
}
}
?>
