<?php
/**
 * Synchronise_Synergetic_Mgs_Strategy_Parent
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>
 */

class Synchronise_Synergetic_Wenona_Strategy_Parent extends Synchronise_Synergetic_Wenona_Strategy_Abstract {

    /**
     * Get a list of users' Synergy IDs to sync
     *
     * @return void
     */
    public function generateList() {
        // custom query provided by Wenona
        $mssqlDB = MSSQL::getInstance();
        $sql = 'SELECT * FROM SynergyOne.dbo.uvCurrentAndNearFutureParents';
        $result = $mssqlDB->getAll($sql);

        $this->collateList($result);

    }

    /**
     * Set up the new user's details
     *
     * @param  $currentUser
     * @return array
     */
    public function setupUserDetails($currentUser) {
        $newUser = array();


        $parent = $this->getExternalUserHandler()->getParent($currentUser['DatabaseID']);


        // Login details
        $newUser['username'] = $currentUser['Login'];
        // $newUser['password'] = "abc123";

        // User details
        $newUser['synergy_id'] = $currentUser['DatabaseID'];
        $newUser['title'] = substr($currentUser['Description'], 0, strpos($currentUser['Description'], ' '));
        $newUser['firstname'] = $currentUser['Given'];
        $newUser['lastname'] = $currentUser['Surname'];
        $newUser['alt_email'] = $parent['Email']; // not mapped

        // the view inverts the boolean campus flags for some reason
        $campus = array();
        if ($currentUser['SeniorSchoolStudent'] == 1) {
            $campus[] = 1;
        }
        if ($currentUser['JuniorSchoolStudent'] == 1) {
            $campus[] = 2;
        }
        if ($currentUser['MiddleSchoolStudent'] == 1) {
            $campus[] = 3;
        }

        if(count($campus) < 1) {
            $campus[] = 1;
        }

        $newUser['defaultCampus'] = $campus[0];

        // get WPA associations
        $newUser['WPA'] = array();
        $newUser['WPA'][] = "Wenona Parents Association (WPA)";

        for ($i = 0; $i < 13; $i++) {
            if ($currentUser["Year{$i}Student"]) {
                if ($i == 0) {
                    $newUser['WPA'][] = "Year Kindergarten Wenona Parents Association";
                } else {
                    $newUser['WPA'][] = sprintf("Year %02d Wenona Parents Association", $i);
                }
            }
        }


        // get campuses of attached children
        $newUser['campus'] = $campus;
        $newUser['group_id'] = self::PARENT_GROUP_ID;

        return $newUser;
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

        $db = DeprecatedDatabase::getInstance();

        $groupHandler = new Group(HOMEPAGE_SERVICE);
        $userHandler = new User();

        $result = $db->getAll("SELECT `id`, `name` FROM `category` WHERE `name` LIKE '%Wenona Parents%'");
        $wpaCats = array();
        // index by name, not id
        foreach ($result as $r) {
            $wpaCats[$r['name']] = $r['id'];
        }

        $this->getVerboseFilter()->intro();

        foreach ($this->getList() as $currentUser) {
            $newUser = $this->setupUserDetails($currentUser);

            /* First iteration
                */
            if ($previousUser == null) {
                if (is_array($newUser['campus'])) {
                    $compiledCampus = $newUser['campus'];
                }

                $previousUser = $newUser;
            } /* Previous was different person to Current
                */
            else if ($previousUser['username'] != $newUser['username']) {
                $previousUser['campus'] = $compiledCampus;

                /* Uniquify campus list
                */
                $previousUser['campus'] = array_unique($previousUser['campus']);

                /* Update previous user
                */
                $this->executeCreateUpdate($previousUser);

                $user = $userHandler->getBySynergy($previousUser['synergy_id']);

                // map parents into their WPA categories
                foreach ($previousUser['WPA'] as $wpa) {
                    if ($this->getPerformUpdate() === true && isset($user['id'])) {
                        echo "^  Adding parent to $wpa\n";
                        $groupHandler->addUser($wpaCats[$wpa], $user['id']);
                    } else {
                        echo "^  Planning to add parent to $wpa\n";
                    }
                }

                /* Recompile
                */
                $compiledCampus = $newUser['campus'];

                $previousUser = $newUser;
            } /* They are the same person
            */
            else {
                $compiledCampus = array_merge($compiledCampus, $newUser['campus']);
            }
        }


        // As of SB-3199, Wenona no longer wants passwords to be created for users.
//        // retrieve all parent records from the user table where passwords are blank, and apply passwords
//        $db = DeprecatedDatabase::getInstance();
//        $users = $db->getAll(
//            "SELECT `id`, `username`
//             FROM `user`
//             WHERE (`password` = '' OR `password` IS NULL)
//             AND `group_id` = " . self::PARENT_GROUP_ID
//        );
//
//        if (is_array($users) && count($users)) {
//            $output = '/tmp/ppw.csv';
//            $fp = fopen($output, 'w');
//            fputcsv($fp, array('login', 'password'));
//
//            foreach ($users as $user) {
//                // plaintext generation needs to be stored: randomly 3 letters, 4 numbers
//                $pw = '';
//                for ($i = 0; $i < 7; $i++) {
//                    $rnd = rand(97, 122);
//                    if ($i < 3) {
//                        $pw .= chr($rnd);
//                    } else {
//                        $pw .= $rnd % 10;
//                    }
//                }
//
//                // store password
//                fputcsv($fp, array($user['username'], $pw));
//
//                if ($this->getPerformUpdate() === true) {
//                    // store password in schoolbox
//                    $userHandler->setPasswordInternal($user['id'], $pw);
//                }
//            }
//            fclose($fp);
//
//            // don't send the email if we didn't update any passwords
//            if ($this->getPerformUpdate() === true) {
//                $m = new Mailer('');
//                $m->setTo('support@alaress.com.au');
//                $m->setSubject('Wenona Parent Sync');
//                $m->addFile($output, 'text/csv');
//                $m->send();
//            }
//            unlink($output);
//        }

        $this->getVerboseFilter()->summary();
    }
}
