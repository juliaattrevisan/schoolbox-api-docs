<?php
/**
 * Hydrator for group for Fintonians.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Spags_Hydrator_Group_Student implements Synchronise_Base_IHydrator {

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['group_id'] = $this->getGroupIDFromUser($externalUser);
        return $newUser;
    }

    /**
     * Groups for Fintonians are based on year level:
     *
     * 30 => 3 (ELC)
     * 40 => 6 (Prep)
     *
     * @param  array $externalUser
     * @return int
     */
    public function getGroupIDFromUser($externalUser) {
        switch ($externalUser['StudentYearLevel']) {
            case 30:
                return 6;
            case 40:
                return 7;
            case 1:
                return 8;
            case 2:
                return 9;
            case 3:
                return 10;
            case 4:
                return 11;
            case 5:
                return 12;
            case 6:
                return 13;
            case 7:
                return 14;
            case 8:
                return 15;
            case 9:
                return 16;
            case 10:
                return 17;
            case 11:
                return 18;
            case 12:
                return 19;
            default:
                return false;
        }
    }
}
