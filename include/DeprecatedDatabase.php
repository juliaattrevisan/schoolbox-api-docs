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
     * Non-deprecated database that this object wraps around.
     *
     * @var Database
     */
    protected $database;

    /**
     * Result from the non-deprecated database.
     *
     * @var PDOStatement
     */
    public $databaseResult;

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
function __construct($db = SQL_db, $debug = 1)
{
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
	unset($this->database);
	$this->connect();
	return true;
}

/**
 * Estabilishes a connection to the database
 *
 * @deprecated
 */
function connect()
{
    // Assuming all DeprecatedDatabase instances use the same connection string
    $this->database = Database::getInstance();
}

	/**
	 * Attempt to set the connection's timezone.
	 *
	 * @param string $timezone A valid timezone.
	 * @return bool
	 */
	public function setTimezone($timezone)
	{
        return $this->database->setTimezone($timezone);
//		return $this->query("SET time_zone = '{$this->quote($timezone)}'");
	}

/**
 * Closes the connection to the database
 *
 * @deprecated
 * @return bool
 */
function close()
{
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
}

/**
 * Executes query on the database and handles any errors
 * Returns the result resource for the query, also saves the result resource as a object var
 *
 * @deprecated
 * @param $sql_q
 * @param bool $retry
 * @return PDOStatement
 */
function query($sql_q, $retry = true)
{
    $this->databaseResult = $this->database->query($sql_q);
    return $this->databaseResult;
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
    return $result->fetchColumn();
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
 * @param PDOStatement $result
 * @return array|bool|null
 */
function getNextArray($result = 0)
{
    if ($result instanceof PDOStatement) {
        $return_array = $result->fetch(PDO::FETCH_ASSOC);
    } elseif ($this->databaseResult instanceof PDOStatement) {
        $return_array = $this->databaseResult->fetch(PDO::FETCH_ASSOC);
    } else {
        return false;
    }
    return $return_array;
}

/**
 * Returns the number of rows returned by the query
 *
 * @deprecated
 * @param PDOStatement $result
 * @return bool|int
 */
function countRows($result = 0)
{
    if ($result instanceof PDOStatement) {
        $return_count = $result->rowCount();
    } elseif ($this->databaseResult instanceof PDOStatement) {
        $return_count = $this->databaseResult->rowCount();
    } else {
        return false;
    }
    return $return_count;
}

/**
 * Inserts an array into table (does all proper security checks to ensure safe)
 *
 * @deprecated use \Schoolbox\Mapper::save()
 * @param $table
 * @param $array
 * @return bool
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
	$this->query($query);
    return true;
}

/**
 * Inserts a multidimension array into table (does all proper security checks to ensure safe)
 *
 * @deprecated use \Schoolbox\Mapper::save()
 * @param $table
 * @param $array
 * @param bool $names
 * @return bool
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

	$this->query($query);
    return true;
}

	/**
	 * Updates a row
	 *
	 * @deprecated use \Schoolbox\Mapper::save()
	 * @param $table
	 * @param $identifier
	 * @param $array
	 * @return bool
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

	$this->query($query);
    return true;
}

/**
 * @deprecated use \Schoolbox\Mapper::delete()
 * @param $table
 * @param $identifier
 * @param $value
 * @return bool
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
	$this->query($query);
    return true;
}


/**
 * Returns the id of the last record inserted
 *
 * @deprecated use \Schoolbox\Mapper::getDatabase()->lastInsertId()
 * @return int|string
 */
function insertID()
{
    return $this->database->lastInsertId();
}

/**
 * Returns the number of rows affected by the last update
 *
 * @deprecated
 * @return int
 */
function affectedRows()
{
    return $this->databaseResult->rowCount();
}

/**
 * Free the result resource
 *
 * @deprecated
 * @param PDOStatement $result
 * @return bool
 */
function endQuery($result = 0)
{
    return true;
}

/**
 * Installs the PHP debug bar collector into this object, and returns it.
 *
 * @deprecated
 */
public function installCollector() {
//    $this->collector = new DeprecatedDatabaseCollector($this->dbf);
}

/**
 * @deprecated
 * @return DeprecatedDatabaseCollector
 */
public function getCollector() {
    return $this->database->getCollector();
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
	$mysqlFuncs = array(
		'NULL',
		'NOW()',
	);

	/* If any non-digits found, assume string. But this might not work for float
	 * or double values, or exponential representations.
	 */
	// Quote if not integer or special
    if (!in_array($value, $mysqlFuncs) ) {
        $value = $this->database->quote($value);
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
    // PDO->quote() adds quote marks - I don't want those.
    return preg_replace("/^'?(.*?)'?$/", '$1', $this->database->quote($string));
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
		$this->__construct();
	}
}
