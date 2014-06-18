<?php
/**
 * Hydrator for group for Cathedral students.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Cathedral_Hydrator_Group_Staff implements Synchronise_Base_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['group_id'] = $this->getGroupIDFromUser($externalUser);
        return $newUser;
    }

    /**
     * Groups for Cathedral students are based on the value of StaffDepartment.
     *
     * Could be:
     * 1:  Administration
     * 2:  Teachers
     * 16: ELC Staff
     *
     * @param  array $externalUser
     * @return int
     */
    public function getGroupIDFromUser($externalUser) {
        switch ($externalUser['StaffDepartment']) {
            case "MAT": // ?
            case "SOC": // ?
            case "ART": // Art
            case "PRE": // Pre-school
            case "":    // ?
            case "LAN": // Language?
            case "SCI": // Science
            case "ENG": // English
            case "PE":  // Phys-Ed
            case "MUS": // Music
            case "OTH": // Other
            case "TUT": // Tutors
            case "MID": // Middle school?
            case "HUM": // Humanities
            case "HPE": // ?
            case "TEC": // Technology
            case "PRI": // Primary?
            case "LOT": // ?
            case "NON": // ?
            case "BBR": // ?
            case "NO":  // ?
            case "GBR": // ?
            default:
        }
    }
}
