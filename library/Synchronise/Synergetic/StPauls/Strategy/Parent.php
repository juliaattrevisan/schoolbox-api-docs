<?php
/**
 * Synchronise_Synergetic_StPauls_Strategy_Parent
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Synergetic_StPauls_Strategy_Parent extends Synchronise_Synergetic_StPauls_Strategy_Abstract {
	protected $strategyTitle = "Update Schoolbox Parent Details Synchronisation";

	/**
	 * Setup
	 */
	protected function setup() {
		$this->setVerboseFilter(new Synchronise_Synergetic_StPauls_Verbose_Parent());
	}

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
		$this->getVerboseFilter()->intro();

		foreach($this->getList() as $currentUser) {

			/* Generate details about the new user
			 */
			$this->executeCreateUpdate($this->setupUserDetails($currentUser));
		}

		$this->getVerboseFilter()->summary();
	}

	/**
	 * Set up the new user's details
	 *
	 * @param  $currentUser
	 * @return array
	 */
	public function setupUserDetails($currentUser) {
		return $currentUser;
	}

	/**
	 * Identical algorithm used to create/update a user
	 *
	 * @param $currentUser
	 */
	public function executeCreateUpdate($currentUser) {
		$currentSynergyId = $currentUser['synergy_id'];

		$currentParent = $this->getExternalUserHandler()->getParentSync($currentSynergyId);

		/* If the parent could be found, try updating him in Synergetic
		 */
		//if ($currentParent) {

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

				$updatedUser['id'] = $user['uid'];
				$updatedUser['username'] = $user['username'];
				$updatedUser['title'] = trim($currentParent['Title']);
				$updatedUser['firstname'] = trim($currentParent['Preferred']);
				$updatedUser['lastname'] = trim($currentParent['Surname']);

				$updatedUser['campus'] = $this->getUserHandler()->getUserCampus($user['uid']);
				$updatedUser['defaultCampus'] = array();
				$updatedUser['password'] = 'password';

				/* Clear email address in Portal if the user is not meant to be
				 * publicly listed
				 */
				if ($this->isPubliclyListed($currentParent)) {
					$updatedUser['alt_email'] = trim($preferredEmail);
				}
				else {
					$updatedUser['alt_email'] = '';
				}

				/* Actually perform db update
				 */
				if ($this->getPerformUpdate() === true) {
					$this->getUserHandler()->update($updatedUser);
					$this->getVerboseFilter()->performUpdate($user, $currentSynergyId, $updatedUser, $currentParent);
					$this->getVerboseFilter()->incrementUpdated();
				}
				else {
					$this->getVerboseFilter()->planUpdate($user, $currentSynergyId, $updatedUser, $currentParent);
					$this->getVerboseFilter()->incrementUpdated();
				}
			}
			else {
				$this->getVerboseFilter()->skip($user, $currentSynergyId);
				$this->getVerboseFilter()->incrementSkipped();
			}
		//}
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
