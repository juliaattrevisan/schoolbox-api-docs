<?php
/**
 * Default hydrator definition for staff..
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Hydrator_Staff implements Synchronise_Base_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['title']      = $externalUser['StaffTitle'];
        $newUser['firstname']  = $externalUser['StaffPreferredName'];
        $newUser['lastname']   = $externalUser['StaffSurname'];
        $newUser['synergy_id'] = $externalUser['StaffID'];
        $newUser['group_id']   = self::DEFAULT_STAFF_GROUP;
        $newUser['alt_email']  = $externalUser['OccupEmail'];
        return $newUser;
    }
}
