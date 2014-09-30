<?php
/**
 * Hydrator for group for Oakburn.
 *
 * @author    James Leckie <james.leckie@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Oakburn_Hydrator_Parent_Username implements Synchronise_Base_IHydrator {

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {

        if(!isset($newUser['synergy_id'])) {
            return false;
        }
        $query = "SELECT Networklogin, initialPassword, WantMail FROM uvParentNetworkLogin WHERE ID = '".$newUser['synergy_id']."'";
        $userViewParent = MSSQL::getInstance()->getRow($query);

        if($userViewParent['WantMail'] != 'No' && $userViewParent['Networklogin'] != '' ) {
            //Create account
            $newUser['username'] = $userViewParent['Networklogin'];
            $newUser['password'] = $userViewParent['initialPassword'];
        } else {
            //Do not create account
            $newUser['username'] = '';
        }

        return $newUser;
    }
}