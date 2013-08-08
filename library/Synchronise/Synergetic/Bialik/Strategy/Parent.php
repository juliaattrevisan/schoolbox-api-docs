<?php
/**
 * Synchronise_Synergetic_Bialik_Strategy_Parent
 *
 * Adds Parents in Synergetic to the Schoolbox User table. Applies Campus based
 * on the Students' Year Level.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Synergetic_Bialik_Strategy_Parent extends Synchronise_Synergetic_Bialik_Strategy_Abstract {
	protected $strategyTitle = "Parent Synchronisation";


    protected $parentPSync = array();

	/**
	 * Get a list of users' Synergy IDs to sync
     * Custom addon for Bialik: also collate the parents' passwords
	 *
	 * @return void
	 */
	public function generateList() {
		$this->collateList($this->getExternalUserHandler()->getParentUserAccountInfo());

        // use defaults to connect to Synergetic for passwords
        $link = MSSQL::getInstance();
        $link->query("
            SELECT vCommunityAddresses.id AS SynergyID,
                   CommunityAttributes.AttributeCode AS CommunityAttribute,
                   CommunityAttributes.AttributeValue AS CommunityPortalPassword,
                   CommunityAttributes.AttributeModifiedDate AS CommunityPortalPasswordDate
            FROM vCommunityAddresses
            LEFT JOIN CommunityAttributes ON CommunityAttributes.ID = vCommunityAddresses.id
            WHERE ConstitCodes LIKE '%@pc%'
            AND CommunityAttributes.AttributeCode = '5'"
        );

        // collate the results, indexed by synID
        while($user = $link->getNextArray()) {
            $this->parentPSync[$user['SynergyID']] = $user;
        }
	}

	/**
	 * Set up the new user's details
	 *
	 * @param  $currentUser
	 * @return array
	 */
	public function setupUserDetails($currentUser) {
		$newUser = array();

		/* Login details
		 */
		$newUser['username'] = $currentUser['StudentContactID'];

        // only set pw variable if there's something to put in it
        $synID = $currentUser['StudentContactID'];
        if(isset($this->parentPSync[$synID])) {
            $newUser['password'] = $this->parentPSync[$synID]['CommunityPortalPassword'];
        }

		/* User details
		 */
        $newUser['synergy_id'] = $currentUser['StudentContactID'];
		$newUser['title'] = $currentUser['StudentContactTitle'];
		$newUser['firstname'] = $currentUser['StudentContactPreferred'];
		$newUser['lastname'] = $currentUser['StudentContactSurname'];
		$newUser['alt_email'] = $currentUser['StudentContactDefaultEmail'];

		/* Campus config: needs to map to a campus id
		 */
		$newUser['defaultCampus'] = 1;
		$newUser['campus'] = array($this->getCampusFromYearLevel($currentUser['StudentYearLevel']));

		/* Staff Group ID
		 */
		$newUser['group_id'] = self::PARENT_GROUP_ID;

		/* This person is not a superuser
		 */
		$newUser['su'] = false;

		return $newUser;
	}

	/**
	 * Set up the new user's spouse's details
	 *
	 * @param $currentUser
	 * @return array
	 */
	public function setupSpouseDetails($currentUser) {
		$spouse = array();

		/* Login details
		 */
		$spouse['username'] = $currentUser['StudentContactSpouseID'];

        // only set pw variable if there's something to put in it
        $synID = $currentUser['StudentContactSpouseID'];
        if(isset($this->parentPSync[$synID])) {
            $spouse['password'] = $this->parentPSync[$synID]['CommunityPortalPassword'];
        }

		/* User details
		 */
		$spouse['synergy_id'] = $currentUser['StudentContactSpouseID'];
		$spouse['title'] = $currentUser['StudentContactSpouseTitle'];
		$spouse['firstname'] = $currentUser['StudentContactSpousePreferred'];
		$spouse['lastname'] = $currentUser['StudentContactSpouseSurname'];
		$spouse['alt_email'] = $currentUser['StudentContactSpouseDefaultEmail'];

		/* Campus config: needs to map to a campus id
		 */
		$spouse['defaultCampus'] = 1;
		$spouse['campus'] = array($this->getCampusFromYearLevel($currentUser['StudentYearLevel']));

		/* Staff Group ID
		 */
		$spouse['group_id'] = self::PARENT_GROUP_ID;

		/* This person is not a superuser
		 */
		$spouse['su'] = false;

		return $spouse;
	}


	/**
	 * If the users exist in Synergy but not in Schoolbox, create an account for
	 * them
	 *
	 * @return void
	 */
	public function createIfExists() {
		$previousUser = null;
		$compiledCampus = array();

		$this->getVerboseFilter()->intro();

		foreach($this->getList() as $currentUser) {
			$newUser = $this->setupUserDetails($currentUser);
			$newSpouse = $this->setupSpouseDetails($currentUser);

			/* First iteration
			 */
			if ($previousUser == null) {
				if (is_array($newUser['campus'])) {
					$compiledCampus = $newUser['campus'];
				}

				$previousUser = $newUser;
				$previousSpouse = $newSpouse;
			}
			/* Previous was different person to Current
			 */
			else if ($previousUser['username'] != $newUser['username']) {
				$previousUser['campus'] = $compiledCampus;
				$previousSpouse['campus'] = $compiledCampus;

				/* Uniquify campus list
				 */
				$previousUser['campus'] = array_unique($previousUser['campus']);
				$previousSpouse['campus'] = array_unique($previousSpouse['campus']);

				/* Update previous user
				 */
				$this->executeCreateUpdate($previousUser);
				$this->executeCreateUpdate($previousSpouse);

				/* Recompile
				 */
				$compiledCampus = $newUser['campus'];

				$previousUser = $newUser;
				$previousSpouse = $newSpouse;
			}
			/* They are the same person
			 */
			else {
				$compiledCampus = array_merge($compiledCampus, $newUser['campus']);
			}
		}

        /* Cleanup last previous user */
        if ($previousUser['username']) {
            $previousUser['campus'] = $compiledCampus;
            $previousSpouse['campus'] = $compiledCampus;

            /* Uniquify campus list
             */
            $previousUser['campus'] = array_unique($previousUser['campus']);
            $previousSpouse['campus'] = array_unique($previousSpouse['campus']);

            /* Update previous user
             */
            $this->executeCreateUpdate($previousUser);
            $this->executeCreateUpdate($previousSpouse);
        }

		$this->getVerboseFilter()->summary();
	}

    /**
     * Do not move parents between groups
     *
     * @param $newUser
     * @return mixed
     */
    protected function userExists($newUser) {
        return $this->userExistsBySynergyAndGroup($newUser);
    }
}