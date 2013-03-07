<?php
/**
 * Synchronise_Synergetic_Balcombe_Strategy_Parent
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Synergetic_Balcombe_Strategy_Parent extends Synchronise_Synergetic_Balcombe_Strategy_Abstract {
	protected $strategyTitle = "Update Schoolbox Parent Details Synchronisation";

	/**
	 * Setup
	 */
	protected function setup() {
		$this->setVerboseFilter(new Synchronise_Synergetic_Balcombe_Verbose_Parent());
	}

	/**
	 * Get a list of users' Synergy IDs to sync
	 *
	 * @return void
	 */
	public function generateList() {
        $this->collateList($this->getExternalUserHandler()->getParentUserAccountInfo());
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
			// Generate details about the new user
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
        $newUser = array();

        $parent = $this->getExternalUserHandler()->getParent($currentUser['StudentContactID']);

        // User details
        $newUser['username'] = $currentUser['StudentContactID'];
        $newUser['synergy_id'] = $currentUser['StudentContactID'];
        $newUser['title'] = $currentUser['StudentContactTitle'];
        $newUser['firstname'] = $currentUser['StudentContactPreferred'];
        $newUser['lastname'] = $currentUser['StudentContactSurname'];
        $newUser['alt_email'] = $this->isPubliclyListed($parent) ? $currentUser['StudentContactDefaultEmail'] : '';

        // get campuses of attached children
        $newUser['campus'] = array(self::DEFAULT_CAMPUS);
        $newUser['group_id'] = self::PARENT_GROUP_ID;
        $newUser['defaultCampus'] = $newUser['campus'][0];

		$newUser = $this->setMailSettingsOnNewUser($newUser);

        return $newUser;
	}

	/**
	 * Determine whether the $currentParent is supposed to be listed publicly
	 *
	 * @param  $currentParent
	 * @return bool
	 */
	private function isPubliclyListed($currentParent) {
        return $currentParent['DirectoryIncludeFlag'] == 0 ? false : true;
	}

    /**
     * Determine if user exists in Schoolbox
     *
     * @param $newUser
     * @return array|bool
     */
    protected function userExists($newUser) {
        $query = "
            SELECT
                    user.id as uid, synergy_id,
                    title, firstname, lastname, username,
                    local_email, alt_email,
                    userCampus.campus, year, house,
                    eportfolio_enabled,
                    groups.name AS gname, group_id
            FROM user
            LEFT JOIN groups ON user.group_id = groups.id
            LEFT JOIN userCampus ON user.id = userCampus.uid
            WHERE user.synergy_id = '{$newUser['synergy_id']}'
        ";

        // Only lock user to group if parent account
        if ($newUser['group_id'] == self::PARENT_GROUP_ID)
            $query .= " AND groups.id = '{$newUser['group_id']}'";


        $query .= " ORDER BY userCampus.defaultCampus DESC";

        return $this->getUserHandler()->db->getRow($query);
    }
}