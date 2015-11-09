<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_External_Fs_Strategy_Student extends Synchronise_External_Fs_Strategy_Abstract {

    /**
     * Get the existing houses from the DB, indexed by name
     * @return array
     */
    protected function getHouses() {
        $stmt = Database::getInstance()->prepare("
            SELECT * FROM `house`
        ");
        $existingHouses = array();
        while($house = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // index by name rather than ID
            $existingHouses[$house['house']] = $house['id'];
        }
        return $existingHouses;
    }

    /**
     * Insert previously-unseen houses into the database
     * @param $newHouses
     */
    protected function insertHouses($newHouses) {
        $stmt = Database::getInstance()->prepare("
            INSERT IGNORE INTO `house` VALUES (:id, :name)
        ");

        foreach($newHouses as $name => $id) {
            $stmt->execute(array('id' => $id, 'name' => $name));
        }
    }

    /**
     * Process user
     */
    public function createIfExists() {
        $this->getVerboseFilter()->intro();

        if(is_array($this->entityData)) {
            $existingHouses = $this->getHouses();
            $newHouses = array();
            $index = count($existingHouses) ? max($existingHouses) : 0;

            foreach($this->entityData as $currentUser) {
                $currentUser['houseName'] = trim($currentUser['houseName']);
                if(!in_array($currentUser['houseName'], array_keys($existingHouses))) {
                    $newHouses[$currentUser['houseName']] = ++$index;
                    // not quite semantically correct, but will allow it to work
                    $existingHouses[$currentUser['houseName']] = $index;
                }

                // add the ID to the user array so it can be inserted in advance of updating the house table
                $currentUser['houseID'] = $existingHouses[$currentUser['houseName']];

                // Generate details about the new user
                $newUser = $this->setupUserDetails($currentUser);

                $this->executeCreateUpdate($newUser);
            }

            // insert new houses into the DB
            if(count($newHouses)) {
                $this->insertHouses($newHouses);
            }

            $this->getVerboseFilter()->summary();
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
        // Login details
        $newUser['username'] = trim($currentUser['networkLogin']);

        // User details
        $newUser['title'] = '';
        $newUser['firstname'] = trim($currentUser['firstName']);
        $newUser['lastname'] = trim($currentUser['lastName']);
        $newUser['synergy_id'] = $currentUser['studentID'];
        $newUser['house'] = $currentUser['houseID'];
        $newUser['alt_email'] = $currentUser['localEmail'];
        $newUser['local_email'] = 1;
        $newUser['year'] = $currentUser['yearLevel'];

        // there is only one campus
        $newUser['defaultCampus'] = 1;
        $newUser['campus'] = array(1);

	// Role ID
	$role = self::STUDENT_GROUP_ID;
	switch($currentUser['yearLevel']) {
		case "12": $role = '6'; break;
		case "11": $role = '7'; break;
		case "10": $role = '8'; break;
		case "9":  $role = '9'; break;
		case "8":  $role = '10'; break;
		case "7":  $role = '11'; break;
		case "6":  $role = '12'; break;
		case "5":  $role = '13'; break;
		case "4":  $role = '14'; break;
		case "3":  $role = '15'; break;
		case "2":  $role = '16'; break;
		case "1":  $role = '17'; break;
		case "P":  $role = '18'; break;
		case "K":  $role = '19'; break;
		case "Year COOP":  $role = '20'; break;
	}
        $newUser['group_id'] = $role;
        // This person is not a superuser
        //$newUser['su'] = false;

        return $newUser;
    }


}
