<?php
/**
 * DB2Statement class to replicate key functionality of PDOStatement for DB2
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class DB2Statement {

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

        return db2_execute($this->statement, $sequential);
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
     * Magic method for
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
}
