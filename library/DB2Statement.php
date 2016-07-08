<?php
/**
 * DB2Statement class to replicate key functionality of PDOStatement for DB2
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/**
 * Class DB2Statement
 * @property string $queryString
 */
class DB2Statement implements Iterator {

    /** @var int $ptr zero-indexed, used for row counting, not item retrieval */
    private $ptr = null;

    /** @var array|bool $current */
    private $current = false;


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
     * @param string $queryString
     * @param resource $statement
     * @param array $varMap
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
            $this->ptr     = -1;
            $this->current = $this->fetchNext();
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
     * Fetches the next row from a result set into $current, increments
     * the pointer, and returns $current.
     *
     * @param int $fetch_style
     * @return mixed
     */
    private function fetchNext($fetch_style = DB2::FETCH_BOTH) {

        ++$this->ptr;

        if($fetch_style == DB2::FETCH_ASSOC) {
            $this->current = db2_fetch_assoc($this->statement);
        }
        elseif($fetch_style == DB2::FETCH_NUM) {
            $this->current = db2_fetch_array($this->statement);
        }
        elseif($fetch_style == DB2::FETCH_OBJ) {
            $this->current = db2_fetch_object($this->statement);
        }
        else {
            $this->current = db2_fetch_both($this->statement);
        }

        return $this->current;
    }

    /**
     * Fetches the current row from the result set, then increments
     * the pointer and loads next result into $current
     *
     * @param int $fetch_style
     * @return mixed
     */
    public function fetch($fetch_style = DB2::FETCH_BOTH) {

        $old_current = $this->current;

        ++$this->ptr;

        if($fetch_style == DB2::FETCH_ASSOC) {
            $this->current = db2_fetch_assoc($this->statement);
        }
        elseif($fetch_style == DB2::FETCH_NUM) {
            $this->current = db2_fetch_array($this->statement);
        }
        elseif($fetch_style == DB2::FETCH_OBJ) {
            $this->current = db2_fetch_object($this->statement);
        }
        else {
            $this->current = db2_fetch_both($this->statement);
        }

        return $old_current;
    }

    /**
     * Returns an array containing all of the result set rows
     *
     * @param int $fetch_style
     * @return array
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

        $old_current = $this->current;

        $this->current = db2_fetch_array($this->statement);

        return isset($old_current[$column_number]) ? $old_current[$column_number] : false;
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
    /*
     * This is not completely trivial. A foreach loop calls the following methods:
     *   rewind()
     *   valid()
     *   -- START LOOP --
     *     current()
     *     next()
     *     valid()
     *   -- END LOOP --
     *
     * Whereas while() repeatedly calls fetch(). That is, fetch() assumes initial pointer at -1
     *
     * To work-around, we fetch the first result on statement execution so we can determine valid(), and
     * load this value into $current. We alter fetch() to logically return $current, then load the next value
     * into $current, rather that load next and return it.
     */

    /**
     * Return the current element
     *
     * @return mixed Can return any type.
     */
    public function current() {
        return $this->current;
    }

    /**
     * Move forward to next element
     *
     * @return void Any returned value is ignored.
     */
    public function next() {
        $this->current = $this->fetchNext();
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
        return false !== $this->current;
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
