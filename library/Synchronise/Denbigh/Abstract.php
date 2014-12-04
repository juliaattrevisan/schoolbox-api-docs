<?php
/**
 * Synchronise_External_Denbigh_Abstract
 *
 * Based on Synchronise_Synergetic_Strategy_Abstract
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Synchronise_Denbigh_Abstract {
    protected $userHandler;
    protected $groupHandler;
    protected $externalUserHandler;
    protected $performUpdate = false;
    protected $list = null;
    protected $strategyTitle = '';
    protected $verbose = true;


    /**
     * Constructor. Optional $title for Strategy being implemented, otherwise classname is used.
     * @param null $title
     */
    public function __construct($title = NULL) {
        $this->setUserHandler(new User());
        $this->setGroupHandler(new Group());
        $this->setExternalUserHandler(new External_User());
        $this->setStrategyTitle($title == NULL ? get_class($this) : $title);
    }


    /**
     * Collate a list of users to add to SBDB
     * @param $allUsers
     */
    protected function collateList($allUsers) {

        $users = array();

        if(is_array($allUsers) && count($allUsers)) {
            foreach($allUsers as $currentUser) {
                $users[] = $currentUser;
            }
        }

        $this->setList($users);
    }


    /**
     * Prints out the actual or intended modification
     * @param  $data
     * @return void
     */
    protected function generateVerboseResult($data) {
        if ($this->getPerformUpdate() === true) {
            $action = "Created account for: ";
        }
        else {
            $action = "Planned creation of: ";
        }

        $str = "$action [" . implode(', ', $data) . "]\n";

        print_r($str);
    }


    /**
     * Print a nice heading before the rest of the confirmation messages
     */
    public function generateTitle() {
        echo "\n------------------------------------------------------------\n";
        echo $this->getStrategyTitle();
        echo "\n------------------------------------------------------------\n";
    }

    /**
     * Handle users who exist in Schoolbox but not Synergetic
     * Blank code block, allowing customisation per site
     */
    public function removeIfNotExists()
    {
    }

    /* Getters and Setters */

    public function setUserHandler($handler) {
        $this->userHandler = $handler;
    }

    public function getUserHandler() {
        return $this->userHandler;
    }

    public function setGroupHandler($handler) {
        $this->groupHandler = $handler;
    }

    public function getGroupHandler() {
        return $this->groupHandler;
    }

    public function setExternalUserHandler($handler) {
        $this->externalUserHandler = $handler;
    }

    public function getExternalUserHandler() {
        return $this->externalUserHandler;
    }

    public function setList($list) {
        $this->list = $list;
    }

    public function getList() {
        return $this->list;
    }

    public function setPerformUpdate($bool) {
        $this->performUpdate = $bool;
    }

    public function getPerformUpdate() {
        return $this->performUpdate;
    }

    public function setStrategyTitle($title) {
        $this->strategyTitle = $title;
    }

    public function getStrategyTitle() {
        return $this->strategyTitle;
    }

    public function setVerbose($bool) {
        $this->verbose = $bool;
    }

    public function getVerbose() {
        return $this->verbose;
    }
}
