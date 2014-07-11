<?php
/**
 * Hydrator for name for Cathedral parents.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Cathedral_Hydrator_Name_Parent implements Synchronise_Base_IHydrator {

    protected $field = 'StudentContactPreferred';

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        // Example Name: "Cpt A Cripps & Mrs P Cripps"
        // Tass ain't giving me titles
        $newUser['firstname'] = '';
        $newUser['lastname']  = $externalUser['StudentContactSurname'];
        return $newUser;
    }
}
