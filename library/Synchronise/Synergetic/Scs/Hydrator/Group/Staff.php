<?php
/**
 * Hydrator for group for Scs.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Scs_Hydrator_Group_Staff implements Synchronise_Base_IHydrator {

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['group_id'] = $this->getGroupIDFromUser($externalUser);

        return $newUser;
    }

    /**
     * Groups for Scs are based on year level
     *
     * @param  array $externalUser
     * @return int
     */
    public function getGroupIDFromUser($externalUser) {
        $year = $externalUser['StaffCategory'];

        if($externalUser['StaffCampus'] == 'J') {
            $teacherGroup = 21;
        }
        else {
            $teacherGroup = 2;
        }

		switch($year) {
			case 'ADMIN': return 6;
			case 'GEN STAFF': return 6;
			case 'CASU': return $teacherGroup;
			case 'TEACH': return $teacherGroup;
			case 'ASST': return $teacherGroup;
			case 'IMT': return $teacherGroup;
			case 'CON': return $teacherGroup;
			case 'CONT': return $teacherGroup;
			default: return $teacherGroup;
        }
    }
}
