<?php
/**
 * Synchronise_Synergetic_Mggs_Verbose
 *
 * Contains methods to output verbose messages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Synergetic_Mggs_Verbose {
	private $verbose = false;

	/**
	 * Echo message about performing update
	 *
	 * @param  $user
	 * @param  $currentSynergyId
	 * @param  $updatedUser
	 * @param  $currentParent
	 * @return void
	 */
	public function performUpdate($user, $currentSynergyId, $updatedUser, $currentParent) {

		/* Update email addresses for publicly listed people
		 * Otherwise clear email address for those who aren't publicly listed
		 */
		if ($this->isPubliclyListed($currentParent)) {
			 $emailString = 'and updating email address';
		}
		else {
			$emailString  = 'and clearing email address';
		}

		$str = array();
		$str[] = "#$user[uid] (Synergetic #$currentSynergyId) $user[title] $user[firstname] $user[lastname] ($user[alt_email])";
		$str[] = "to $updatedUser[title] $updatedUser[firstname] $updatedUser[lastname] ($updatedUser[alt_email])";
		$str[] = "$emailString\n";

		$summary = (implode(' ', $str));

		echo "+  Performing update of $summary\n";
	}

	/**
	 * Echo message about planning update
	 *
	 * @param  $user
	 * @param  $currentSynergyId
	 * @param  $updatedUser
	 * @param  $currentParent
	 * @return void
	 */
	public function planUpdate($user, $currentSynergyId, $updatedUser, $currentParent) {
		if ($this->getVerbose() === true) {

			/* Update email addresses for publicly listed people
			 * Otherwise clear email address for those who aren't publicly listed
			 */
			if ($this->isPubliclyListed($currentParent)) {
				 $emailString = 'and updating email address';
			}
			else {
				$emailString  = 'and clearing email address';
			}

			$str = array();
			$str[] = "#$user[uid] (Synergetic #$currentSynergyId) $user[title] $user[firstname] $user[lastname] ($user[alt_email])";
			$str[] = "to $updatedUser[title] $updatedUser[firstname] $updatedUser[lastname] ($updatedUser[alt_email])";
			$str[] = "$emailString\n";

			$summary = (implode(' ', $str));

			echo "^  Planning update of $summary\n";
		}
	}

	/**
	 * Echo message about skipping create/update
	 *
	 * @param  $user
	 * @param  $currentSynergyId
	 * @return void
	 */
	public function skip($user, $currentSynergyId) {
		if ($this->getVerbose() === true) {
			$str = array();
			$str[] = 'Ignoring';
			$str[] = "#$user[uid] (Synergetic #$currentSynergyId) $user[title] $user[firstname] $user[lastname] ($user[alt_email])\n";

			$summary = implode(' ', $str);

			echo "o  Skipping create/update of $summary\n";
		}
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
}
