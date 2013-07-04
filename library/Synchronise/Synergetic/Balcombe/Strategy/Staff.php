<?php
/**
 * Synchronise_Synergetic_Balcombe_Strategy_Staff
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Synchronise_Synergetic_Balcombe_Strategy_Staff extends Synchronise_Synergetic_Balcombe_Strategy_Abstract {
	protected $strategyTitle = "Update Schoolbox Staff Details Synchronisation";

	const T_GROUP_ID = 4;
	const IT_GROUP_ID = 6;
	const LC_GROUP_ID = 4;
	const LT_GROUP_ID = 4;
	const N_GROUP_ID = 6;
	const SC_GROUP_ID = 4;

	/**
	 * Setup
	 */
	protected function setup() {
		$this->setVerboseFilter(new Synchronise_Synergetic_Balcombe_Verbose_Staff());
	}

	/**
	 * Get a list of users' Synergy IDs to sync
	 *
	 * @return void
	 */
	public function generateList() {
		$this->collateList($this->getExternalUserHandler()->getStaffUserAccountInfo());
	}

    /**
     * Set up the new user's details
	 *
	 * @param  $currentUser
	 * @return array
	 */
	public function setupUserDetails($currentUser) {
		$newUser = array();

        // User details
        $newUser['username'] = $currentUser['NetworkLogin'];
		$newUser['title'] = $currentUser['StaffTitle'];
		$newUser['firstname'] = $currentUser['StaffPreferredName'];
		$newUser['lastname'] = $currentUser['StaffSurname'];
        $newUser['synergy_id'] = $currentUser['StaffID'];
		$newUser['alt_email'] = $currentUser['OccupEmail'];

		// Campus config: needs to map to a campus id
		$newUser['defaultCampus'] = $this->getCampusFromCode($currentUser['StaffCampus']);
		$newUser['campus'] = array($this->getCampusFromCode($currentUser['StaffCampus']));

		// Staff Group ID
		$newUser['group_id'] =  $this->getGroupIDFromCategory($currentUser['StaffCategory']);

		$newUser = $this->setMailSettingsOnNewUser($newUser);

		return $newUser;
	}

	/**
	 * If the users exist in Synergy but not in Schoolbox, create an account for them
	 *
	 * @return void
	 */
	public function createIfExists() {
		$this->getVerboseFilter()->intro();

		foreach($this->getList() as $currentUser) {
			// Generate details about the new user
			$newUser = $this->setupUserDetails($currentUser);

			// Default campus to 1, but only if no campuses were determined (use array filter to remove empty elements)
			if (!count(array_filter($newUser['campus']))) {
				$newUser['campus'] = array(self::DEFAULT_CAMPUS);
			}

			$this->executeCreateUpdate($newUser);
		}

		$this->getVerboseFilter()->summary();
	}

    /**
     * Converts Synergetic StaffCategoryType to admin or teacher group
     *
     * @param $category
     * @return bool|int
     */
    protected function getGroupIDFromCategory($category) {
        if(defined('self::'.$category.'_GROUP_ID')){
			return constant('self::'.$category.'_GROUP_ID');
        }
        return false;
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
