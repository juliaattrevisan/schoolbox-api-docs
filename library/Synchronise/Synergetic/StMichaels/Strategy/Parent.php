<?php
/**
 * Synchronise_Synergetic_StMichaels_Strategy_Parent
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Synergetic_StMichaels_Strategy_Parent extends Synchronise_Synergetic_StMichaels_Strategy_Abstract {

    const PARENT_GROUP_ID = 3;

    protected $strategyTitle = "Update Schoolbox Parent Details Synchronisation";

	/**
	 * Setup
	 */
	protected function setup() {
		$this->setVerboseFilter(new Synchronise_Synergetic_StMichaels_Verbose_Parent());
	}

	/**
	 * Get a list of users' Synergy IDs to sync
	 *
	 * @return void
	 */
	public function generateList() {
        // start by retrieving a list of parents from the regular part of Synergetic
        $parentAccountListUnindexed = $this->getExternalUserHandler()->getParentUserAccountInfo();

        // index returned parents by synergy id
        $parentUserAccounts = array();
        for($i = 0; $i < count($parentAccountListUnindexed); ++$i) {
            $parentUserAccounts[$parentAccountListUnindexed[$i]['StudentContactID']] = $parentAccountListUnindexed[$i];
        }

        // and now we can get rid of this duplication
        unset($parentAccountListUnindexed);

        // get the parent list from the passwords table
        $link = new MSSQL();
        $link->query("SELECT * FROM uvParentLogin");

        // iterate across the password list and fold the details list into it where possible
        $parentList = array();
        $nonExistent = array();
        while($parentLogin = $link->getNextArray()) {
            $synID = $parentLogin['SynergeticID'];
            if(isset($parentUserAccounts[$synID])) {
                $parent = $parentUserAccounts[$synID];
                $parent['password'] = $parentLogin['UserPassword'];
                $parentList[] = $parent;
            }
            else {
                $nonExistent[] = $synID;
            }
        }

        printf("Parent Sync Notice: %d logins linked to accounts, %d accounts do not exist\n",
            count($parentList),
            count($nonExistent)
        );

		$this->collateList($parentList);
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

		$currentSynergyId = $currentUser['StudentContactID'];

		$currentParent = $this->getExternalUserHandler()->getParentSync($currentSynergyId);

		// If the parent could be found, try updating him in Synergetic
		if ($currentParent) {

			// The drop down in synergetic's Phone and Occupation tabs indicates which email the person prefers to use.
			if ($currentParent['DefaultEmailCode'] == 'O') {
				$preferredEmail = $currentParent['OccupEmail'];
			}
			else {
				$preferredEmail = $currentParent['Email'];
			}

            // we can't know whether or not the password has been changed, always sync all parents...no skipping
			$user = $this->getUserHandler()->getBySynergy($currentSynergyId);

            // user exists in schoolbox: perform update
            if($user) {
                // use immutable data from schoolbox here rather than synergy
                $targetUser['id'] = $user['uid'];
                $targetUser['username'] = $user['username'];
                $targetUser['synergy_id'] = $user['synergy_id'];
            }
            // user does not exist, retrieve immutable fields from synergetic
            else {
                // ['id'] is not populated because this is a create
                $targetUser['username'] = $currentParent['ID'];
                $targetUser['synergy_id'] = $currentParent['ID'];
            }

            // insert password
            $targetUser['password'] = $currentUser['password'];

            // from here on, use synergy data
            $targetUser['title'] = trim($currentParent['Title']);
            $targetUser['firstname'] = trim($currentParent['Preferred']);
            $targetUser['lastname'] = trim($currentParent['Surname']);
            $targetUser['group_id'] = self::PARENT_GROUP_ID;

            $targetUser['campus'] = $this->getUserHandler()->getUserCampus($user['uid']);
            $targetUser['defaultCampus'] = array();

            $targetUser['alt_email'] = $this->isPubliclyListed($currentParent) ? trim($preferredEmail) : '';

            $this->getVerboseFilter()->setNewUser($targetUser);

            // perform update
            if($user) {
                if ($this->getPerformUpdate() === true) {
                    $this->getUserHandler()->update($targetUser);
                    $this->getVerboseFilter()->performUpdate($user, $currentSynergyId, $targetUser, $currentParent);
                    $this->getVerboseFilter()->incrementUpdated();
                }
                else {
                    $this->getVerboseFilter()->planUpdate($user, $currentSynergyId, $targetUser, $currentParent);
                    $this->getVerboseFilter()->incrementUpdated();
                }
            }
            // perform create
            else {
                if ($this->getPerformUpdate() === true) {
                    $this->getUserHandler()->create($targetUser);
                    $this->getVerboseFilter()->performCreation();
                    $this->getVerboseFilter()->incrementCreated();
                }
                else {
                    $this->getVerboseFilter()->planCreation();
                    $this->getVerboseFilter()->incrementCreated();
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