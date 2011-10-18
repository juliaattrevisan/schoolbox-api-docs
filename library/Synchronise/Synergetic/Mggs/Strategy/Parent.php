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
class Synchronise_Synergetic_Mggs_Strategy_Parent extends Synchronise_Synergetic_Mggs_Strategy_Abstract {
	protected $strategyTitle = "Update Schoolbox Parent Details Synchronisation";

	/**
	 * Get a list of users' Synergy IDs to sync
	 *
	 * @return void
	 */
	public function generateList() {
		$this->collateList($this->getUserHandler()->getByGroup($this->getGroupHandler()->getParentGroupTypeIds(), true));
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

						$this->getVerboseFilter()->performUpdate($user, $currentSynergyId, $updatedUser, $currentParent);
					}
					else {
						$this->getVerboseFilter()->planUpdate($user, $currentSynergyId, $updatedUser, $currentParent);
					}
				}
				else {
					$this->getVerboseFilter()->skip($user, $currentSynergyId);
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
}