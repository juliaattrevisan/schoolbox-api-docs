<?php
/**
 * Synchronise_Synergetic_Bialik_Verbose
 *
 * Contains methods to output verbose messages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Synergetic_Bialik_Verbose {
	private $verbose = false;
	private $newUser = array();

	/* Stats
	 */
	private $numCreated = 0;
	private $numUpdated = 0;
	private $numSkipped = 0;

	/**
	 * Echo message about skipping all actions
	 *
	 * @return void
	 */
	public function skip() {
		if ($this->getVerbose() === true) {
			echo "o  Skipping create/update of " . $this->verboseUserSummary($this->getNewUser()) . " because they have no username.\n";
		}
	}

	/**
	 * Echo message about planning creation
	 *
	 * @return void
	 */
	public function planCreation() {
		if ($this->getVerbose() === true) {
			echo "+  Planning creation of " . $this->verboseUserSummary($this->getNewUser()) . "\n";
		}
	}

	/**
	 * Echo message about performing creation
	 *
	 * @return void
	 */
	public function performCreation() {
		if ($this->getVerbose() === true) {
			echo "+  Performing creation of " . $this->verboseUserSummary($this->getNewUser()) . "\n";
		}
	}

	/**
	 * Echo message about planning update
	 *
	 * @return void
	 */
	public function planUpdate() {
		if ($this->getVerbose() === true) {
			echo "^  Planning update of " . $this->verboseUserSummary($this->getNewUser()) . "\n";
		}
	}

	/**
	 * Echo message about performing update
	 *
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
		$data[] = "password: {$newUser['password']}";
		$data[] = "title: {$newUser['title']}";
		$data[] = "firstname: {$newUser['firstname']}";
		$data[] = "lastname: {$newUser['lastname']}";
		$data[] = "alt_email: {$newUser['alt_email']}";
		$data[] = "campus: " . '[' . implode(',', $newUser['campus']) . ']';
		$data[] = "groupIDs: " . $newUser['group_id'];

		return '(' . implode(', ', $data) . ')';
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

	public function incrementCreated() {
		$this->numCreated++;
	}

	public function incrementUpdated() {
		$this->numUpdated++;
	}

	public function incrementSkipped() {
		$this->numSkipped++;
	}

	public function getNumCreated() {
		return $this->numCreated;
	}

	public function getNumUpdated() {
		return $this->numUpdated;
	}

	public function getNumSkipped() {
		return $this->numSkipped;
	}

	/**
	 * A summary count of changes
	 *
	 * @return void
	 */
	public function summary() {
		echo "\n";
		echo "Created: {$this->getNumCreated()}, ";
		echo "Updated: {$this->getNumUpdated()}, ";
		echo "Skipped: {$this->getNumSkipped()}";
		echo "\n\n";
	}
}
