<?php
/**
 * Default hydrator definition.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Hydrator_Student implements Synchronise_Base_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {

        /*
         * ALWAYS title = externalUser.StudentTitle
         * AND firstname = externalUser.StudentPreferred
         * AND lastname = externalUser.StudentSurname
         * AND synergyId = externalUser.StudentID
         * AND year = externalUser.StudentYearLevel
         * AND role = Student (3)
         */
        $newUser['title']      = $externalUser['StudentTitle'];
        $newUser['firstname']  = $externalUser['StudentPreferred'];
        $newUser['lastname']   = $externalUser['StudentSurname'];
        $newUser['synergy_id'] = $externalUser['StudentID'];
        $newUser['year']       = $externalUser['StudentYearLevel'];
        $newUser['group_id']   = self::DEFAULT_STUDENT_GROUP;

        /*
         * TODO smarts: 'blank' = strlen(trim($value))
         *
         * IF externalUser.StudentOccupEmail NOT BLANK
         * THEN altEmail = externalUser.StudentOccupEmail
         */
        if (strlen(trim($externalUser['StudentOccupEmail']))) {
            $newUser['alt_email'] = $externalUser['StudentOccupEmail'];
        } else {
            $newUser['alt_email'] = '';
        }
        return $newUser;
    }
}
