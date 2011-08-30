<?php
/**
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database. This was formerly known as parentSync.php
 * and was amalgamated with the Bialik sync classes.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class UpdateParentSynergeticSyncStrategy extends SynergeticSyncStrategy implements SynergeticSyncStrategyInterface {
	protected $strategyTitle = "Update Schoolbox Parent Details Synchronisation";

	/**
	 * Get a list of users' Synergy IDs to sync
	 *
	 * @return void
	 */
	public function generateList() {
		$this->collateList($this->getUserHandler()->getByGroup(unserialize(PARENTGROUPIDS), true));
	}

	/**
	 * If the users exist in Synergy but not in Schoolbox, create an account for
	 * them
	 *
	 * @return void
	 */
	public function createIfExists() {
		foreach($this->getList() as $currentUser) {
			$currentSynergyId = $currentUser['synergy_id'];

			$currentParent = $this->getExternalUserHandler()->getParentSync($currentSynergyId);

			/* If the parent could be found, try updating him in Synergetic
			 */
			if ($currentParent) {

				/* The drop down in synergetic's Phone and Occupation tabs indicates
				 * which email the person prefers to use.
				 */
				if ($currentParent['DefaultEmailCode'] == 'O') {
					$preferredEmail = $currentParent['OccupEmail'];
				}
				else {
					$preferredEmail = $currentParent['Email'];
				}

				/* Update Portal with Synergetic data if there is something
				 * different contained in Synergetic db
				 */
				$user = $this->getUserHandler()->getBySynergy($currentSynergyId);

				if (($user['title'] != $currentParent['Title']) || ($user['firstname'] != $currentParent['Preferred']) || ($user['lastname'] != $currentParent['Surname']) || ($user['alt_email'] != $preferredEmail)) {
					$updatedUser = $this->getUserHandler()->getById($user['uid']);

					$updatedUser['id'] = $user['uid'];
					$updatedUser['title'] = trim($currentParent['Title']);
					$updatedUser['firstname'] = trim($currentParent['Preferred']);
					$updatedUser['lastname'] = trim($currentParent['Surname']);

					$updatedUser['campus'] = $this->getUserHandler()->getUserCampus($user['uid']);
					$updatedUser['defaultCampus'] = array();

					/* Clear email address in Portal if the user is not meant to be
					 * publicly listed
					 */
					if ($this->isPubliclyListed($currentParent)) {
						$updatedUser['alt_email'] = trim($preferredEmail);
					}
					else {
						$updatedUser['alt_email'] = '';
					}

					unset($updatedUser['gname']);
					unset($updatedUser['uid']);

					/* Actually perform db update
					 */
					if ($this->getPerformUpdate() === true) {
						$this->getUserHandler()->update($updatedUser);
					}

					$this->updateVerboseResult($user, $currentSynergyId, $updatedUser, $currentParent);
				}
				else {
					$this->ignoreVerboseResult($user, $currentSynergyId);
				}
			}
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

	/**
	 * Verbose feedback for potential update action
	 *
	 * @param  $currentUser
	 * @return void
	 */
	private function updateVerboseResult($user, $currentSynergyId, $updatedUser, $currentParent) {
		if ($this->getVerbose() === true) {
			if ($this->getPerformUpdate() === true) {
				$action = 'Updating';
			}
			else {
				$action = 'Planning update';
			}

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
			$str[] = "$action on";
			$str[] = "#$user[uid] (Synergetic #$currentSynergyId) $user[title] $user[firstname] $user[lastname] ($user[alt_email])";
			$str[] = "to $updatedUser[title] $updatedUser[firstname] $updatedUser[lastname] ($updatedUser[alt_email])";
			$str[] = "$emailString\n";

			print_r(implode(' ', $str));
		}
	}

	/**
	 * Verbose feedback for ignore-update action
	 *
	 * @param  $user
	 * @param  $currentSynergyId
	 * @return void
	 */
	private function ignoreVerboseResult($user, $currentSynergyId) {
		if ($this->getVerbose() === true) {
			$str = array();
			$str[] = 'Ignoring';
			$str[] = "#$user[uid] (Synergetic #$currentSynergyId) $user[title] $user[firstname] $user[lastname] ($user[alt_email])\n";

			print_r(implode(' ', $str));
		}
	}
}