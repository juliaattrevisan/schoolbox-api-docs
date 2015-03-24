<?php
/**
 * Synchronise_Synergetic_Jpc_Strategy_Parent
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Synergetic_Jpc_Strategy_Parent extends Synchronise_Synergetic_Jpc_Strategy_Abstract {
	protected $strategyTitle = "Update Schoolbox Parent Details Synchronisation";

    /**
     * A list of synced parent IDs
     *
     * @var array
     */
    protected $syncedExternalIds = array();

    /**
     * Role ID for parents
     */
    const PARENT_ROLE_ID = 4;

    /**
     * Role ID for past parents
     */
    const PAST_PARENT_ROLE_ID = 16;

	/**
	 * Setup
	 */
	protected function setup() {
		$this->setVerboseFilter(new Synchronise_Synergetic_Jpc_Verbose_Parent());
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

        // set parents not touched by this sync into previous parents role.
        foreach ($this->getUserHandler()->getByGroup(self::PARENT_ROLE_ID) as $existingParent) {
            $synergyId = $existingParent['synergy_id'];
            if (!$this->isExternalIdSynced($synergyId)) {
                $newParent             = $existingParent;
                $newParent['group_id'] = self::PAST_PARENT_ROLE_ID;
                $synergyParent         = $this->getExternalUserHandler()->getParentSync($synergyId);
                if (!empty($synergyParent)) {
                    if ($this->getPerformUpdate() === true) {
                        $this->getUserHandler()->update($newParent);
                        $this->getVerboseFilter()->performUpdate(
                            $existingParent,
                            $synergyId,
                            $newParent,
                            $synergyParent
                        );
                    } else {
                        $this->getVerboseFilter()->planUpdate(
                            $existingParent,
                            $synergyId,
                            $newParent,
                            $synergyParent
                        );
                    }
                    $this->getVerboseFilter()->incrementUpdated();
                } else {
                    $this->getVerboseFilter()->skip($existingParent, $synergyId);
                    $this->getVerboseFilter()->incrementSkipped();
                }
            }
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

				$updatedUser['id'] = $user['uid'];
                $updatedUser['username'] = $user['username'];
				$updatedUser['title'] = trim($currentParent['Title']);
				$updatedUser['firstname'] = trim($currentParent['Preferred']);
				$updatedUser['lastname'] = trim($currentParent['Surname']);

				$updatedUser['campus'] = $this->getUserHandler()->getUserCampus($user['uid']);
				$updatedUser['defaultCampus'] = $updatedUser['campus'][0];

				/* Clear email address in Portal if the user is not meant to be
				 * publicly listed
				 */
				if ($this->isPubliclyListed($currentParent)) {
					$updatedUser['alt_email'] = trim($preferredEmail);
				}
				else {
					$updatedUser['alt_email'] = '';
				}

                // Store that this user was updated
                $this->setExternalIdAsSynced($currentSynergyId);

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
     * Sets this external ID as having been synced.
     *
     * @todo implement something like this for the base sync
     *
     * @param string $externalId
     */
    protected function setExternalIdAsSynced($externalId)
    {
        $this->syncedExternalIds[$externalId] = true;
    }

    /**
     * Is this external ID synced?
     *
     * @param string $externalId
     *
     * @return bool
     */
    protected function isExternalIdSynced($externalId)
    {
        return array_key_exists($externalId, $this->syncedExternalIds);
    }
}
