<?php
/**
 * Hydrator for name for Meriden parents.
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
        if (preg_match('/^(\\w+)\\s+(.*)$/', $externalUser[$this->field], $matches)) {
            list(, $newUser['firstname'], $newUser['lastname']) = $matches;
        }
        return $newUser;
    }
}
