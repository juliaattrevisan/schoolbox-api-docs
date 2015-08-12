<?php
/************************************************************
*                                                           *
* 		Copyright (c) 2013  Alaress Pty Ltd     			*
*                                                           *
*	This file provides abstraction of the database.			*
*	This implements MySQL connections.  All standard 		*
*	functions are accessible plus some incapsulated 		*
*	functions like getSingleRow and getAllRows.				*
*	It also includes error handling and a debug mode.		*
*                                                           *
************************************************************/

use Alaress\DebugBar\DataCollector\DeprecatedDatabaseCollector;

/**
 * @deprecated
 * Class DeprecatedDatabase
 */
class DeprecatedDatabase implements Serializable
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

/** @var self */
static private $instance = false;

/**
 * @var Alaress\DebugBar\DataCollector\DeprecatedDatabaseCollector
 */
protected $collector;

/**
 * Constuctor sets up variables and connects to database
 *
 * @deprecated use \Schoolbox\Mapper
 * @param string $db
 * @param int $debug
 */
function DeprecatedDatabase($db = SQL_db, $debug = 1)
{
	$this->host = SQL_host;
	$this->user = SQL_user;
	$this->passwd = SQL_passwd;
	$this->debug = $debug;
	$this->db = $db;
	$this->connect();
}

/**
 * @deprecated
 * @return bool|DeprecatedDatabase
 */
static public function getInstance() {
        if (!self::$instance) {
                self::$instance = new self();
        }
        return self::$instance;
}

/**
 * @deprecated
 */
static public function reinitialise() {
	self::$instance->reconnect();
}

/**
 * @deprecated
 * @return bool
 */
function reconnect()
{
	$closure = mysqli_close($this->dbf);
	unset($this->dbf);
	$this->connect();
	return $closure;
}

/**
 * Estabilishes a connection to the database
 *
 * @deprecated
 */
function connect()
{
	if(!isset($this->dbf)) {
		$this->dbf = mysqli_connect($this->host, $this->user, $this->passwd);
		mysqli_set_charset($this->dbf, 'UTF8');
	}
	if(!$this->dbf instanceof mysqli) {
		die("Unable to connect ".$this->user."@".$this->host." using ".$this->db);
	}
	if(!$this->changeDb($this->db))
		print "Unable to access ".$this->db.", check the permissions";
}

	/**
	 * Attempt to set the connection's timezone.
	 *
	 * @param string $timezone A valid timezone.
	 * @return bool
	 */
	public function setTimezone($timezone)
	{
		return $this->query("SET time_zone = '{$this->quote($timezone)}'");
	}

/**
 * Closes the connection to the database
 *
 * @deprecated
 * @return bool
 */
function close()
{
	return mysqli_close($this->dbf);
}

/**
 * Changes the current selected database
 *
 * @deprecated
 * @param $db
 * @return bool
 */
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

/**
 * Executes query on the database and handles any errors
 * Returns the result resource for the query, also saves the result resource as a object var
 *
 * @deprecated
 * @param $sql_q
 * @param bool $retry
 * @return bool|mysqli_result|resource
 */
function query($sql_q, $retry = true)
{
	if ($this->debug && $this->collector instanceof DeprecatedDatabaseCollector) {
		$this->result = $this->collector->query($sql_q);
	}
	else {
		$this->result = mysqli_query($this->dbf, $sql_q) or $mysqli_eval_error = mysqli_error($this->dbf);

		if (isset($mysqli_eval_error)) {
            if($retry) {
                $this->reconnect();
                return $this->query($sql_q, false);
            }

			if ($this->debug) {
				trigger_error(sprintf('Query: "%s" triggered error: "%s"', preg_replace('`\s+`', ' ', $sql_q), $mysqli_eval_error), E_USER_WARNING);
			}
			else {
				error(21, $mysqli_eval_error);
			}
		}
	}

	return $this->result;
}

/**
 * Returns the current result resource
 *
 * @deprecated
 * @return mysqli_result
 */
function getResult()
{
	return $this->result;
}

/**
 * Gets the first result returned by the query
 * Returns an associative array of row, false if none exists
 *
 * @deprecated
 * @param $sql_q
 * @return array|bool|null
 */
function getRow($sql_q)
{
	$result = $this->query($sql_q);
	$row = $this->getNextArray($result);
	return $row;
}

/**
 * Gets the first value of the first result returned by the query
 * Returns an associative array of row, false if none exists
 *
 * @deprecated
 * @param $sql_q
 * @return mixed
 */
function getOne($sql_q)
{
	$result = $this->query($sql_q);
	$row = mysqli_fetch_row($result);
	return $row[0];
}

/**
 * Gets an 3 dimensional associative array of all rows returned by query,
 * returns false if no rows
 *
 * @param $sql_q
 * @return array|bool
 */
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

/**
 * Returns the next result from a query result resource
 * Can be optionally passed a result resource otherwise it uses current result resource
 *
 * @deprecated
 * @param int $result
 * @return array|bool|null
 */
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

/**
 * Returns the number of rows returned by the query
 *
 * @deprecated
 * @param int $result
 * @return bool|int
 */
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

/**
 * Inserts an array into table (does all proper security checks to ensure safe)
 *
 * @deprecated use \Schoolbox\Mapper::save()
 * @param $table
 * @param $array
 * @return bool|mysqli_result|resource
 */
function insert($table, $array)
{
	if(!($table && is_array($array)))
		return FALSE;

	$names = '';
	$values = '';

	foreach($array as $key => $value)
	{
		$name = '`'.$key.'`' . ',';
		if (!is_array($value)) {
			$names  .= $name;
			$values .= $this->smartQuote($value) . ',';
		}
		elseif (isset($value['function'])) {
			$names  .= $name;
			$values .= $value['function']. '('. $this->smartQuote($value['value']). '),';
		}
	}
	$names = substr($names, 0, -1);
	$values = substr($values, 0, -1);

	$query = "INSERT INTO `$table` ($names) VALUES ($values)";
	return $this->query($query);
}

/**
 * Inserts a multidimension array into table (does all proper security checks to ensure safe)
 *
 * @deprecated use \Schoolbox\Mapper::save()
 * @param $table
 * @param $array
 * @param bool $names
 * @return bool|mysqli_result|resource
 */
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

	/**
	 * Updates a row
	 *
	 * @deprecated use \Schoolbox\Mapper::save()
	 * @param $table
	 * @param $identifier
	 * @param $array
	 * @return bool|mysqli_result|resource
	 */
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

/**
 * @deprecated use \Schoolbox\Mapper::delete()
 * @param $table
 * @param $identifier
 * @param $value
 * @return bool|mysqli_result|resource
 */
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


/**
 * Returns the id of the last record inserted
 *
 * @deprecated use \Schoolbox\Mapper::getDatabase()->lastInsertId()
 * @return int|string
 */
function insertID()
{
	$return_id = mysqli_insert_id($this->dbf);
	return $return_id;
}

/**
 * Returns the number of rows affected by the last update
 *
 * @deprecated
 * @return int
 */
function affectedRows()
{
	$return_count = mysqli_affected_rows($this->dbf);
	return $return_count;
}

/**
 * Free the result resource
 *
 * @deprecated
 * @param int $result
 * @return bool
 */
function endQuery($result = 0)
{
	if ($result)
		mysqli_free_result($result);
	else if($this->result)
		mysqli_free_result($this->result);
	else
		return false;
}

/**
 * Installs the PHP debug bar collector into this object, and returns it.
 *
 * @deprecated
 */
public function installCollector() {
    $this->collector = new DeprecatedDatabaseCollector($this->dbf);
}

/**
 * @deprecated
 * @return DeprecatedDatabaseCollector
 */
public function getCollector() {
    return $this->collector;
}

/* PRIVATE FUNCTIONS */

/**
 * Quote variable to make safe for sql use
 *
 * @deprecated
 * @param $value
 * @return string
 */
function smartQuote($value) {

// Stripslashes
	if (get_magic_quotes_gpc()) {
		$value = stripslashes($value);
	}

	$mysqlFuncs = array(
		'NULL',
		'NOW()',
	);

	/* If any non-digits found, assume string. But this might not work for float
	 * or double values, or exponential representations.
	 */
	// Quote if not integer or special
    if (!in_array($value, $mysqlFuncs) ) {
        $value = "'" . mysqli_real_escape_string($this->dbf, $value) . "'";
    }

	return $value;
}

/**
 * Escapes for safe input into database. Unlike smartQuote(), doesn't coerce
 * 'null' to NULL or wrap values in quote marks.
 *
 * @deprecated
 * @param  string $string
 * @return string
 */
public function quote($string) {
    return mysqli_real_escape_string($this->dbf, $string);
}

/**
 * Escapes _ and % characters within $string, so they don't have unintended
 * effects in LIKE queries.
 *
 * @deprecated
 * @param  string $string
 * @return string
 */
public function quoteForLike($string) {
    return strtr(
        $this->quote($string),
        array(
            '_' => '\\_',
            '%' => '\\%',
        )
    );
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


	/**
	 * String representation of object
	 *
	 * @return string
	 */
	public function serialize() {
		return null;
	}

	/**
	 * Constructs the object
	 *
	 * @param string $serialized
	 * @return void
	 */
	public function unserialize($serialized) {
		$this->DeprecatedDatabase();
	}
}
