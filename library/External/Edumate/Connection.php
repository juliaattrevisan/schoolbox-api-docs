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
     * @var PDO
     */
    private $database;

    /**
     * Build a generic connection to an Edumate db
     */
    public function __construct () {
        // TODO : CONFIRM -- external_db_host can contain the DSN
        $this->setDatabase(new PDO(Cfg::get('external_db_host'), Cfg::get('external_db_user'), Cfg::get('external_db_passwd')));
        $this->setGroupHandler(new Group());
        $this->getUserHandler(new User());
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
     * @return PDO
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
}
