<?php
/**
 * Default hydrator definition.
 *
 * Usage: in s
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Strategy_Hydrator_Student implements Synchronise_Base_Strategy_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['title']      = $externalUser['StudentTitle'];
        $newUser['firstname']  = $externalUser['StudentPreferred'];
        $newUser['lastname']   = $externalUser['StudentSurname'];
        $newUser['synergy_id'] = $externalUser['StudentID'];
        $newUser['year']       = $externalUser['StudentYearLevel'];
        $newUser['group_id']   = self::DEFAULT_STUDENT_GROUP;
        if (strlen(trim($externalUser['StudentOccupEmail']))) {
            $newUser['alt_email'] = $externalUser['StudentOccupEmail'];
        }
        return $newUser;
    }

//    /**
//     * Overridden to use getBySynergy() method - students often switch groups.
//     *
//     * @param  array $user
//     *
//     * @return array
//     */
//    protected function userExists($user) {
//        return $this->user_handler->getBySynergy($user['synergy_id']);
//    }
}
