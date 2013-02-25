<?php
/**
 * Synchronise_Synergetic_Moriah_Strategy_Parent
 *
 * Adds Parents in Synergetic to the Schoolbox User table. Applies Campus based
 * on the Students' Year Level.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Synergetic_Moriah_Strategy_Parent extends Synchronise_Synergetic_Moriah_Strategy_Abstract {
	protected $strategyTitle = "Parent Synchronisation";
	const PARENT_GROUP_ID = 19;

    private $parentCategories = array();
    private $removeQuery;

    public function __construct() {
        parent::__construct();

        $this->groupHandler = new Group(HOMEPAGE_SERVICE);

        $db = Database::getInstance();
        $result = $db->query("
            SELECT `id`, `name`
            FROM `category`
            WHERE `name` LIKE '%Information'
            AND `parent` = '1197'
        ");

        // index by name, not id
        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $this->parentCategories[$row['name']] = $row['id'];
        }

        $this->removeQuery = $db->prepare("
          DELETE
          FROM userAccess
          WHERE service = 1
          AND uid = :parentId
          AND sid IN (
            SELECT id
            FROM `category`
            WHERE `name` LIKE '%Information'
            AND parent = '1197'
          )
        ");
    }


	/**
	 * Get a list of users' Synergy IDs to sync
     * Custom addon for Moriah: also collate the parents' passwords
	 *
	 * @return void
	 */
	public function generateList() {
		$this->collateList($this->getExternalUserHandler()->getParentUserAccountInfo());
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
        // $newUser['password'] = '';

		/* User details
		 */
        $newUser['synergy_id'] = $currentUser['StudentContactID'];
		$newUser['title'] = $currentUser['StudentContactTitle'];
		$newUser['firstname'] = $currentUser['StudentContactPreferred'];
		$newUser['lastname'] = $currentUser['StudentContactSurname'];
		$newUser['alt_email'] = $currentUser['StudentContactDefaultEmail'];

        // get Parent Category associations
        $newUser['ParentCat'] = array("Parent Information");

//        printf(" ** %s **\n", $currentUser['StudentYearLevel']);

        if($currentUser['StudentYearLevel'] == 0) {
            $newUser['ParentCat'][] = "Year 0-K Information";
        }
        elseif($currentUser['StudentYearLevel'] < 13) {
            $newUser['ParentCat'][] = sprintf("Year %02d Information", $currentUser['StudentYearLevel']);
        }
        elseif($currentUser['StudentYearLevel'] <= 50) {
            $newUser['ParentCat'][] = "Preschool Information";
        }

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
        // $spouse['password'] = '';

		/* User details
		 */
		$spouse['synergy_id'] = $currentUser['StudentContactSpouseID'];
		$spouse['title'] = $currentUser['StudentContactSpouseTitle'];
		$spouse['firstname'] = $currentUser['StudentContactSpousePreferred'];
		$spouse['lastname'] = $currentUser['StudentContactSpouseSurname'];
		$spouse['alt_email'] = $currentUser['StudentContactSpouseDefaultEmail'];

        // get Parent Category associations
        $spouse['ParentCat'] = array("Parent Information");

        if($currentUser['StudentYearLevel'] == 0) {
            $spouse['ParentCat'][] = "Year O-K Information";
        }
        elseif($currentUser['StudentYearLevel'] < 13) {
            $spouse['ParentCat'][] = sprintf("Year %02d Information", $currentUser['StudentYearLevel']);
        }
        elseif($currentUser['StudentYearLevel'] <= 50) {
            $newUser['ParentCat'][] = "Preschool Information";
        }

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
        $compiledGroups = array();

		$this->getVerboseFilter()->intro();

		foreach($this->getList() as $currentUser) {

			$newUser = $this->setupUserDetails($currentUser);
			$newSpouse = $this->setupSpouseDetails($currentUser);

            /* First iteration
                */
			if ($previousUser == null) {
				if (is_array($newUser['campus'])) {
					$compiledCampus = $newUser['campus'];
                    $compiledGroups = $newUser['ParentCat'];
				}

				$previousUser = $newUser;
				$previousSpouse = $newSpouse;
			}
			/* Previous was different person to Current
			 */
			else if ($previousUser['username'] != $newUser['username']) {
				$previousUser['campus'] = $compiledCampus;
				$previousSpouse['campus'] = $compiledCampus;
                $previousUser['ParentCat'] = $compiledGroups;
                $previousSpouse['ParentCat'] = $compiledGroups;

                /* Uniquify campus list
                     */
                $previousUser['campus'] = array_unique($previousUser['campus']);
                $previousSpouse['campus'] = array_unique($previousSpouse['campus']);
                $previousUser['ParentCat'] = array_unique($previousUser['ParentCat']);
                $previousSpouse['ParentCat'] = array_unique($previousSpouse['ParentCat']);

                // echo 'compiling... '; print_r($previousUser['ParentCat']); echo "\n";

                /* Update previous user
                     */
                foreach(array($previousUser, $previousSpouse) as $user) {
                    $cats = $user['ParentCat'];
                    unset($user['ParentCat']);
                    $this->executeCreateUpdate($user);
                    $dbUser = $this->userHandler->getBySynergyAndGroup($user['synergy_id'], self::PARENT_GROUP_ID);
                    $dbUser['ParentCat'] = $cats;
                    $this->unenrolFromParentGroups($dbUser);
                    $this->enrolIntoParentGroups($dbUser);
                }

				/* Recompile
				 */
				$compiledCampus = $newUser['campus'];
				$compiledGroups = $newUser['ParentCat'];

				$previousUser = $newUser;
				$previousSpouse = $newSpouse;
			}
			/* They are the same person
			 */
			else {
				$compiledCampus = array_merge($compiledCampus, $newUser['campus']);
                $compiledGroups = array_merge($compiledGroups, $newUser['ParentCat']);


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
            foreach(array($previousUser, $previousSpouse) as $user) {
                $cats = $user['ParentCat'];
                unset($user['ParentCat']);
                $this->executeCreateUpdate($user);
                $dbUser = $this->userHandler->getBySynergyAndGroup($user['synergy_id'], self::PARENT_GROUP_ID);
                $dbUser['ParentCat'] = $cats;
                $this->unenrolFromParentGroups($dbUser);
                $this->enrolIntoParentGroups($dbUser);
            }

        }

		$this->getVerboseFilter()->summary();
	}


    /*
     * Function to unenrol a parent from the appropriate groups
     */
    private function unenrolFromParentGroups($user) {
        if(!isset($user['uid'])) {
            return false;
        }

        if ($this->getPerformUpdate() === true) {
            // remove parent from any existing parent membership groups
            $this->removeQuery->execute(array('parentId' => $user['uid']));
            echo "^  Removing parent {$user['username']} from all Parent groups\n";
        }
        else {
            echo "^  Planning to remove parent {$user['username']} from all Parent groups\n";
        }

        return true;
    }


    /**
     * Function to enrol a parent into their appropriate groups
     * @param $user
     */
    private function enrolIntoParentGroups($user) {


        if(!isset($user['uid'])) {
//            echo 'quitting out of enrolment';
            return false;
        }
        // var_dump($user['ParentCat']);

        //        echo "wendy\n";

        foreach ($user['ParentCat'] as $pcat) {
            if ($this->getPerformUpdate() === true) {
                echo "^  Adding parent {$user['username']} to $pcat\n";
                $this->groupHandler->addUser($this->parentCategories[$pcat], $user['uid']);
            }
            else {
                echo "^  Planning to add parent {$user['username']} to $pcat\n";
            }
        }

        return true;
    }
}