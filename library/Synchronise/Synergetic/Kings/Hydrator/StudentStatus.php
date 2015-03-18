<?php
/**
 * Hydrator for user status for Kings users.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Kings_Hydrator_StudentStatus implements Synchronise_Base_IHydrator {
    public function hydrate($externalUser, $newUser) {
		$this->external_user_handler = new External_User();
    	$student = $this->external_user_handler->getStudent($externalUser['StudentID']);
    	switch ($student['StudentBoarder']) {
    		case 'W':
    			$boardingStatus = 'Weekly Boarder';
    			break;
    		case 'B':
    			$boardingStatus = 'Boarder';
    			break;
    		case 'D':
    		default:
    			$boardingStatus = 'Day Boy';
    			break;
    	}
    	$newUser['boarding_status'] = $boardingStatus;
        return $newUser;
    }
}
