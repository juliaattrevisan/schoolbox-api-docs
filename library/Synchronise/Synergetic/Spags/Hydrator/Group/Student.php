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
     * * 30 => 3 (ELC)
     * * 40 => 6 (Prep)
     * * Otherwise => year+6
     *
     * @param  array $externalUser
     * @return int
     */
    public function getGroupIDFromUser($externalUser) {
        $year = $externalUser['StudentYearLevel'];
        switch ($year) {
            case 30:
                return 3;
            case 40:
                return 6;
            default:
                return $year+6;
        }
    }
}
