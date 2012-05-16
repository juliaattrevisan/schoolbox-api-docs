<?php
/**
 * Synchronise_External_Kings_Verbose
 *
 * Kings-specific Verbose Output Handler
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Denbigh_Kings_Verbose {
    private $verbose = true;
    private $newUser = array();

    // Stats
    private $numCreated = 0;
    private $numUpdated = 0;
    private $numSkipped = 0;


    /**
     * Echo message about skipping all actions
     * @return void
     */
    public function skip() {
        if ($this->getVerbose() === true) {
            echo "o  Skipping create/update of " . $this->verboseUserSummary($this->getNewUser()) . " because they have no username.\n";
        }
    }


    /**
     * Echo message about planning creation
     * @return void
     */
    public function planCreation() {
        if ($this->getVerbose() === true) {
            echo "+  Planning create of " . $this->verboseUserSummary($this->getNewUser()) . "\n";
        }
    }


    /**
     * Echo message about performing creation
     * @return void
     */
    public function performCreation() {
        if ($this->getVerbose() === true) {
            echo "+  Performing create of " . $this->verboseUserSummary($this->getNewUser()) . "\n";
        }
    }

    /**
     * Echo message about planning update
     * @return void
     */
    public function planUpdate() {
        if ($this->getVerbose() === true) {
            echo "^  Planning update of " . $this->verboseUserSummary($this->getNewUser()) . "\n";
        }
    }

    /**
     * Echo message about performing update
     * @return void
     */
    public function performUpdate() {
        if ($this->getVerbose() === true) {
            echo "^  Performing update of " . $this->verboseUserSummary($this->getNewUser()) . "\n";
        }
    }

    /**
     * Create a verbose summary string containing data about the
     * current user being examined
     *
     * @param  $newUser
     * @return string
     */
    private function verboseUserSummary($newUser) {
        $data = array();
        $data[] = "username: {$newUser['username']}";
        //$data[] = "password: {$newUser['password']}";
        $data[] = "title: {$newUser['title']}";
        $data[] = "firstname: {$newUser['firstname']}";
        $data[] = "lastname: {$newUser['lastname']}";
        $data[] = "email: {$newUser['local_email']}";
        //$data[] = "campus: " . '[' . implode(',', $newUser['campus']) . ']';
        //$data[] = "groupIDs: " . $newUser['group_id'];
        $data[] = "residential house: {$newUser['residential_house_id']}";
        if (array_key_exists('year', $newUser)) {
            $data[] = 'year: ' . $newUser['year'];
        }

        return '(' . implode(', ', $data) . ')';
    }

    /**
     * Create an intro
     * @return void
     */
    public function intro() {
        echo "This script automatically updates Schoolbox with data found in Denbigh.\n\n";

        echo "There are two modes: 'Planning' and 'Performing'.\n\n";

        echo "Planning mode: activated using \$sync->setPerformUpdate(false);\n";
        echo "This allows you to see a preview of what is going to happen. No database changes are made.\n\n";

        echo "Performing mode: activated using \$sync->setPerformUpdate(true);\n";
        echo "This allows the script to modify the database.\n\n\n";

        echo "Legend ------------------------------------------------------\n\n";
        echo "+  Planning create: The script intends to create a Schoolbox account with data found in Synergetic.\n";
        echo "^  Planning update: The script intends to update a Schoolbox account with data found in Synergetic.\n\n";

        echo "+  Performing create: The script will create a Schoolbox account with data found in Synergetic.\n";
        echo "^  Performing update: The script will to update a Schoolbox account with data found in Synergetic.\n\n";

        echo "o  Sipping create/update: The script intends to ignore this record altogether, regardless of mode.\n\n";
        echo "-------------------------------------------------------------\n";
        echo "\n\n";
    }

    /**
     * A summary count of changes
     * @return void
     */
    public function summary() {
        echo "\n";
        echo "Created: {$this->getNumCreated()}, ";
        echo "Updated: {$this->getNumUpdated()}, ";
        echo "Skipped: {$this->getNumSkipped()}";
        echo "\n\n";
    }

    /**
     * Set new user
     *
     * @param  $val
     * @return void
     */
    public function setNewUser($val) {
        $this->newUser = $val;
    }

    /**
     * Get new user
     *
     * @return
     */
    private function getNewUser() {
        return $this->newUser;
    }

    /**
     * Set Verbose estting
     *
     * @param  $val
     * @return void
     */
    public function setVerbose($val) {
        $this->verbose = $val;
    }

    /**
     * Get Verbose setting
     *
     * @return bool
     */
    private function getVerbose() {
        return $this->verbose;
    }

    /**
     * Increment the number of items created
     *
     * @return void
     */
    public function incrementCreated() {
        $this->numCreated++;
    }

    /**
     * Increment the number of items updated
     *
     * @return void
     */
    public function incrementUpdated() {
        $this->numUpdated++;
    }

    /**
     * Increment the number of items skipped
     *
     * @return void
     */
    public function incrementSkipped() {
        $this->numSkipped++;
    }

    /**
     * Get number of items created
     *
     * @return int
     */
    public function getNumCreated() {
        return $this->numCreated;
    }

    /**
     * Get number of items updated
     *
     * @return int
     */
    public function getNumUpdated() {
        return $this->numUpdated;
    }

    /**
     * Get number of items skipped
     *
     * @return int
     */
    public function getNumSkipped() {
        return $this->numSkipped;
    }
}
