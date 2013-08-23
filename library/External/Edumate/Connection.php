<?php
/**
 * Provides the common connection functionality to the Edumate connection classes
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class External_Edumate_Connection {

    /**
     * @var Group
     */
    private $groupHandler;

    /**
     * @var User
     */
    private $userHandler;

    /**
     * @var DB2
     */
    private $database;

    /**
     * Build a generic connection to an Edumate db
     */
    public function __construct () {
        // build a DSN from the config vars
        $host = Cfg::get('external_db_host');
        $db = Cfg::get('external_db_db');
        $un = Cfg::get('external_db_user');
        $pw = Cfg::get('external_db_passwd');

        $dsn = "DATABASE=$db;";
        $hosts = explode(':', $host);

        if(count($hosts) == 2) {
            $dsn .= "HOSTNAME={$hosts[0]};PORT={$hosts[1]};";
        }
        elseif(count($hosts) == 1) {
            $dsn .= "HOSTNAME={$hosts[0]};";
        }

	    try {
	        $this->setDatabase(new DB2($dsn, $un, $pw));
	        $this->setGroupHandler(new Group());
	        $this->setUserHandler(new User());
	    } catch(PDOException $e) {
		    /// chain off the PDO exception and throw an External instead to be caught by the interface class
		    throw new \Schoolbox\External\Exception($e->getMessage(), $e->getCode(), $e);
	    }
    }

    /**
     * Set the database connector
     * @param $database
     */
    protected function setDatabase ($database) {
        $this->database = $database;
    }

    /**
     * Retrieve the database connector
     * @return DB2
     */
    protected function getDatabase() {
        return $this->database;
    }

    /**
     * Set the group handler
     * @param Group $group
     */
    protected function setGroupHandler($group) {
        $this->groupHandler = $group;
    }

    /**
     * Retrieve the group handler
     * @return Group
     */
    protected function getGroupHandler() {
        return $this->groupHandler;
    }

    /**
     * Set the user handler
     * @param $user
     */
    protected function setUserHandler($user) {
        $this->userHandler = $user;
    }

    /**
     * Retrieve the user handler
     * @return User
     */
    protected function getUserHandler() {
        return $this->userHandler;
    }

    /**
     * Internal DB2-specific code to turn CAPITALISEDFIELDS into NotCapitalisedFields
     *
     * @param array $record
     * @param array $fieldNames
     * @return array
     */
    protected function normaliseFields(array $record, array $fieldNames) {
        foreach($fieldNames as $field) {
            $upper = strtoupper($field);
            if(array_key_exists($upper, $record)) {
                $record[$field] = $record[$upper];
                unset($record[$upper]);
            }
        }
        return $record;
    }
}
