<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_External_Lahore_Strategy_Student extends Synchronise_External_Lahore_Strategy_Abstract {

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

        // Staff Group ID
        $newUser['group_id'] = self::STUDENT_GROUP_ID;

        // This person is not a superuser
        //$newUser['su'] = false;

        return $newUser;
    }


}
