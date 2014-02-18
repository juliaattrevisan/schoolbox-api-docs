<?php
/**
 * Hydrator for name for Meriden parents.
 *
 * Tass sticks both parents into the "parents" table, so I'm just going to
 * create a single 'family' account.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Meriden_Hydrator_Name_Parent implements Synchronise_Base_IHydrator {

    protected $field = 'StudentContactPreferred';

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        // Name: "$lastname Family"
        $newUser['firstname'] = '';
        $newUser['lastname']  = $externalUser['StudentContactSurname'].' Family';
        return $newUser;
    }
}
