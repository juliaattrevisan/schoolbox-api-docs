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

        // replicate the query from the synergetic user handler and join it to the passwords table
        $link = new MSSQL();

        $query = "
			SELECT DISTINCT
				StudentContactID, StudentContactTitle, StudentContactPreferred, StudentContactSurname,
				StudentContactDefaultEmail, StudentContactOccupEmail, c1.DefaultEmailCode, c1.DirectoryIncludeFlag,
				c1.NetworkLogin StudentContactNetworkLogin, pl1.UserPassword StudentContactPassword,

				StudentContactSpouseID, StudentContactSpouseTitle, StudentContactSpousePreferred,
				StudentContactSpouseSurname, StudentContactSpouseDefaultEmail, StudentContactSpouseOccupEmail,
				c2.DefaultEmailCode SpouseDefaultEmailCode, c2.DirectoryIncludeFlag SpouseDirectoryIncludeFlag,
				c2.NetworkLogin StudentContactSpouseNetworkLogin, pl2.UserPassword StudentContactSpousePassword,

				StudentYearLevel
			FROM SynergyOne.dbo.vStudentContactAllAddress vscaa

			LEFT JOIN SynergyOne.dbo.Community c1 ON vscaa.StudentContactID = c1.ID
			LEFT JOIN uvParentLogin pl1 ON vscaa.StudentContactID = pl1.SynergeticID

			LEFT JOIN SynergyOne.dbo.Community c2 ON vscaa.StudentContactSpouseID = c2.ID
			LEFT JOIN uvParentLogin pl2 ON vscaa.StudentContactSpouseID = pl2.SynergeticID

			WHERE (StudentContactType = 'SC1' OR StudentContactType = 'SC2')
			AND FileSemester = '{$_SESSION['FileSemester']}'
			AND FileYear = '{$_SESSION[ 'FileYear']}'
			ORDER BY StudentContactID
		";

        $parentList = $link->getAll($query);
		$this->collateList($parentList);
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
			$this->executeCreateUpdate($this->setupUserDetails($currentUser));
			$this->executeCreateUpdate($this->setupSpouseDetails($currentUser));
		}

		$this->getVerboseFilter()->summary();
	}


	/**
	 * Set up the new user's details: effectively, map the synergy data into schoolbox fields
	 *
	 * @param  $currentUser
	 * @return array
	 */
	public function setupUserDetails($currentUser) {
        return $this->setupParentDetails($currentUser);
	}


    /**
     * Set up the spouse details of the current user: effectively, map the synergy data into schoolbox fields
     *
     * @param $currentUser
     * @return array
     */
    public function setupSpouseDetails($currentUser) {
        return $this->setupParentDetails($currentUser, 'Spouse');
    }


    /**
     * Internal helper function for setting up both primary contacts and spouses without duplicating code
     *
     * @param $currentUser
     * @param string $fieldVariant
     * @return array
     */
    protected function setupParentDetails($currentUser, $fieldVariant = '') {
        $targetUser = array();

        $synergyId = $currentUser["StudentContact{$fieldVariant}ID"];

        // if the conect id is blank or 0 or what have you, don't attempt to continue
        if(!$synergyId) {
            return false;
        }

        $user = $this->getUserHandler()->getBySynergyAndGroup($synergyId, self::PARENT_GROUP_ID);

        if($user) {
            // use immutable data from schoolbox here rather than synergy
            $targetUser['id'] = $user['uid'];
            $targetUser['username'] = $user['username'];
            $targetUser['synergy_id'] = $user['synergy_id'];
        }
        // user does not exist, retrieve immutable fields from synergetic
        else {
            // ['id'] is not populated because this is a create
            $targetUser['username'] = $currentUser["StudentContact{$fieldVariant}NetworkLogin"];
            $targetUser['synergy_id'] = $currentUser["StudentContact{$fieldVariant}ID"];
        }

        // from here on, use synergy data
        $targetUser['password'] = $currentUser["StudentContact{$fieldVariant}Password"];
        $targetUser['title'] = trim($currentUser["StudentContact{$fieldVariant}Title"]);
        $targetUser['firstname'] = trim($currentUser["StudentContact{$fieldVariant}Preferred"]);
        $targetUser['lastname'] = trim($currentUser["StudentContact{$fieldVariant}Surname"]);
        $targetUser['group_id'] = self::PARENT_GROUP_ID;

        // get campus list from children's campuses, as already stored in Schoolbox
        $targetUser['campus'] = array();
        $relatedIDs = $this->getExternalUserHandler()->getEnrolledStudents($synergyId);

        if(is_array($relatedIDs)) {
            foreach($relatedIDs as $row) {
                $child = $this->getUserHandler()->getBySynergy($row['RelatedID']);
                $campuses = $this->getUserHandler()->getUserCampus($child['uid']);
                $targetUser['campus'] = array_merge($targetUser['campus'], $campuses);
            }
            $targetUser['campus'] = array_unique($targetUser['campus']);
        }

        $targetUser['defaultCampus'] = count($targetUser['campus']) ? $targetUser['campus'][0] : false;

        // Figure out a) whether to show an email or not, and if so, b) which one.
        if($currentUser["{$fieldVariant}DirectoryIncludeFlag"] == 0) {
            // show no email
            $targetUser['alt_email'] = '';
        }
        else {
            // choose an email
            $targetUser['alt_email'] = ($currentUser["{$fieldVariant}DefaultEmailCode"] == 'O')
                ? $currentUser["StudentContact{$fieldVariant}OccupEmail"]
                : $currentUser["StudentContact{$fieldVariant}DefaultEmail"];
        }

        return $targetUser;
    }


	/**
	 * Identical algorithm used to create/update a user
	 *
	 * @param $currentUser
	 */
	public function executeCreateUpdate($currentUser) {

        // no details, will be a user record with no spouse
        if(!is_array($currentUser)) {
            return;
        }

        $this->getVerboseFilter()->setNewUser($currentUser);

        // perform update if the user has an existing Schoolbox ID set
        if(isset($currentUser['id'])) {
            if ($this->getPerformUpdate() === true) {
                $this->getUserHandler()->update($currentUser);
                $this->getVerboseFilter()->performUpdate();
                $this->getVerboseFilter()->incrementUpdated();
            }
            else {
                $this->getVerboseFilter()->planUpdate();
                $this->getVerboseFilter()->incrementUpdated();
            }
        }
        // perform create
        else {
            if ($this->getPerformUpdate() === true) {
                $this->getUserHandler()->create($currentUser);
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