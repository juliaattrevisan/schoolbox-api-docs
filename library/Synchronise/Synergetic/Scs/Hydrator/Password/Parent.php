<?php
/**
 * Hydrator for parent passwords
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Scs_Hydrator_Password_Parent extends Synchronise_Base_Hydrator_CreateUpdate
{
    /**
     * A parent's default password is based off their first name, last name and
     * synergy ID, in this fashion:
     *
     * "{firstname initial}{lastname initial}{synergyId}
     */
    public function hydrateOnCreate($externalUser, $newUser)
    {
        $encoding = 'UTF-8';
        $newUser['password'] = sprintf(
            '%s%s%s',
            mb_strtolower(mb_substr($newUser['firstname'], 0, 1, $encoding), $encoding),
            mb_strtolower(mb_substr($newUser['lastname'], 0, 1, $encoding), $encoding),
            $newUser['synergy_id']
        );
        return $newUser;
    }
}
