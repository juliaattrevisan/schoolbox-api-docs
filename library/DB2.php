<?php
/**
 * DB2 class to replicate key functionality of PDO for DB2
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class DB2 {

    const FETCH_ASSOC = PDO::FETCH_ASSOC;
    const FETCH_BOTH = PDO::FETCH_BOTH;
    const FETCH_NUM = PDO::FETCH_NUM;
    const FETCH_OBJ = PDO::FETCH_OBJ;

    /**
     * @var resource
     */
    private $db2;

    /**
     * Creates a DB2 instance representing a connection to a database
     *
     * @param $dsn String in the form DATABASE=$database;HOSTNAME=$hostname;PORT=$port;
     * @param $username String
     * @param $password String
     * @param $options array
     * @throw PDOException
     */
    public function __construct($dsn, $username = '', $password = '', array $options = array()) {
        // compile the username and password into the DSN (it's dirty, but necessary
        $connDSN = sprintf("DRIVER={IBM DB2 JDBC DRIVER};%sPROTOCOL=TCPIP;UID=%s;PWD=%s;", $dsn, $username, $password);

        // check the function actually exists
        if(!function_exists('db2_connect')) {
            throw new PDOException('DB2 extension is not installed. Please contact your Network Administrator.');
        }

        if(!($this->db2 = db2_connect($connDSN, '', '', $options))) {
            throw new PDOException('Could not connect to DB2: attempted connection string was '.$connDSN);
        }
    }

    /**
     * Executes an SQL statement, returning a result set as a DB2Statement object
     *
     * @param $statement string
     * @return DB2Statement
     */
    public function query($statement) {
        if($result = db2_exec($this->db2, $statement)) {
            return new DB2Statement($statement, $result);
        }
        return false;
    }

    /**
     * Prepares a statement for execution and returns a statement object
     *
     * @param $statement string
     */
    public function prepare($statement) {

        /*
         * DB2 does not support named variables, so this method needs to turn the associative array provided into a
         * numeric array and replace :tokens with ?s.
         */

        // extract the tokens, in the order they appear
        $varMap = preg_match_all("#:([A-Za-z0-9-_]+)#", $statement, $matches) ? $matches[1] : null;
        // replace :tokens with ?s (if there are none, no harm done ... but then why bother with prepare?)
        $statement = preg_replace("#:[A-Za-z0-9-_]+#", '?', $statement);

        if($prep = db2_prepare($this->db2, $statement)) {
            return new DB2Statement($statement, $prep, $varMap);
        }
        return false;

    }

    /**
     * Returns the ID of the last inserted row or sequence value
     *
     * @return string
     */
    public function lastInsertId() {
        return db2_last_insert_id($this->db2);
    }

    /**
     * Execute an SQL statement and return the number of affected rows
     *
     * @param $statement string
     * @return int|bool
     */
    public function exec($statement) {
        $stmt = $this->query($statement);
        return $stmt->rowCount();
    }

}
