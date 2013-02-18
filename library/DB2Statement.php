<?php
/**
 * DB2Statement class to replicate key functionality of PDOStatement for DB2
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class DB2Statement implements Iterator {

    private $ptr = null;
    private $valid = false;

    /**
     * The SQL being executed
     *
     * @var string
     */
    private $queryString;

    /**
     * A pointer to the database response
     *
     * @var resource
     */
    private $statement;

    /**
     * Array containing the mapping of vars from associative to indexed
     *
     * @var array
     */
    private $varMap;

    /**
     * @param $queryString
     * @param $statement
     */
    public function __construct($queryString, $statement, array $varMap = null) {
        $this->queryString = $queryString;
        $this->statement = $statement;
        $this->varMap = $varMap;
    }

    /**
     * Executes a prepared statement
     *
     * @param array $input_parameters
     * @return bool
     */
    public function execute(array $input_parameters = null) {
        $sequential = array();

        // iterate across the tokens in the varMap and extract the vars in the input params
        if(is_array($this->varMap)) {
            foreach($this->varMap as $index => $token) {
                if(!isset($input_parameters[$token])) {
                    throw new PDOException('Input parameters must identically match tokens in prepared statement');
                }
                $sequential[$index] = $input_parameters[$token];
            }
        }

        $success = db2_execute($this->statement, $sequential);

        if($success) {
            $this->ptr = 0;
            $this->valid = true;
        }
        else {
            // non-fatal errors can occur here, so don't want them displayed but would like a log of them
            $logFile = Config::get('logs_dir') . '/' . 'db2.err';
            $fp = fopen($logFile, 'a');
            fwrite($fp, sprintf("[%s] %s", date("Y-m-d H:i:s"), db2_stmt_errormsg($this->statement)));
            fclose($fp);
        }

        return $success;
    }

    /**
     * Fetches the next row from a result set
     */
    public function fetch($fetch_style = DB2::FETCH_BOTH) {
        if($fetch_style == DB2::FETCH_ASSOC) {
            $result = db2_fetch_assoc($this->statement);
        }
        elseif($fetch_style == DB2::FETCH_NUM) {
            $result = db2_fetch_array($this->statement);
        }
        elseif($fetch_style == DB2::FETCH_OBJ) {
            $result = db2_fetch_object($this->statement);
        }
        else {
            $result = db2_fetch_both($this->statement);
        }

        return $result;
    }

    /**
     * Returns an array containing all of the result set rows
     *
     * @param int $fetch_style
     */
    public function fetchAll($fetch_style = DB2::FETCH_BOTH) {
        $result = array();

        while($record = $this->fetch($fetch_style)) {
            $result[] = $record;
        }

        return $result;
    }

    /**
     * Returns a single column from the next row of a result set
     *
     * @param int $column_number
     * @return string
     */
    public function fetchColumn($column_number = 0) {
        $result = db2_fetch_array($this->statement);

        return isset($result[$column_number]) ? $result[$column_number] : false;
    }

    /**
     * Returns the number of rows affected by the last SQL statement
     *
     * @return int
     */
    public function rowCount() {
        return db2_num_rows($this->statement);
    }

    /**
     * Magic method for returning the query string
     *
     * @param $name
     * @return mixed
     */
    public function __get($name) {
        if($name == 'queryString') {
            return $this->queryString;
        }
        return null;
    }


    /*** ITERATOR METHODS FOR MAXIMUM PDO COMPATIBILITY ***/

    /**
     * Return the current element
     *
     * @return mixed Can return any type.
     */
    public function current() {
        $fetch = $this->fetch();
        if($fetch === false) {
            $this->valid = false;
        }
        return $fetch;
    }

    /**
     * Move forward to next element
     *
     * @return void Any returned value is ignored.
     */
    public function next() {
        ++$this->ptr;
    }

    /**
     * Return the key of the current element
     *
     * @return mixed scalar on success, or null on failure.
     */
    public function key() {
        return $this->ptr;
    }

    /**
     * Checks if current position is valid
     *
     * @return boolean The return value will be casted to boolean and then evaluated.
     *       Returns true on success or false on failure.
     */
    public function valid() {
        return $this->valid;
    }

    /**
     * Rewind the Iterator to the first element
     *
     * @return void Any returned value is ignored.
     */
    public function rewind() {
        // cursor cannot be rewound, do nothing
    }

}
