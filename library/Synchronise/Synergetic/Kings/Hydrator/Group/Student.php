<?php
/**
 * Hydrator for Default Group for Kings users.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Kings_Hydrator_Group_Student implements Synchronise_Base_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['group_id'] = $this->getGroupIDfromYearLevel($externalUser['StudentYearLevel']);
        return $newUser;
    }

	protected function getGroupIDFromYearLevel($yearLevel) {
        //
        // Students in Primary school: group 5, otherwise group 3.
        if ($yearLevel < 7) {
            return 5;
        }
        return 3;
    }

}



