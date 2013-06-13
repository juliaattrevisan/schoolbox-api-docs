<?php
/**
 * Synchronise_Synergetic_StPauls_Verbose_Parent
 *
 * Contains methods to output verbose messages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Synergetic_StPauls_Verbose_Parent extends Synchronise_Synergetic_Verbose_Abstract {
	protected $verbose = false;

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
		$str[] = "$emailString";

		$summary = (implode(' ', $str));

		echo "^  Performing update of $summary\n";
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
				$emailString  = 'and clearing email address because it is not publicly listed';
			}

			$str = array();
			$str[] = "#{$user['uid']} (Synergetic #{$currentSynergyId}) {$user['title']} {$user['firstname']} {$user['lastname']} ({$user['alt_email']})";
			$str[] = "to {$updatedUser['title']} {$updatedUser['firstname']} {$updatedUser['lastname']} ({$updatedUser['alt_email']})";
			$str[] = "{$emailString}";

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
			$str[] = "#$user[uid] (Synergetic #$currentSynergyId) $user[title] $user[firstname] $user[lastname] ($user[alt_email])";

			$summary = implode(' ', $str);

			echo "o  Skipping create/update of $summary\n";
		}
	}

	/**
	 * Determine whether the $currentParent is supposed to be listed publicly
	 *
	 * @param  $currentParent
	 * @return bool
	 */
	private function isPubliclyListed($currentParent) {
		if ($currentParent['DirectoryIncludeFlag'] == 0) {
			return false;
		}

		return true;
	}
}
