<?php
/**
 * Set the user to use username@email_domain as their email address
 * by setting the local_email attribute
 *
 * @author    James Leckie <jleckie@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Hydrator_NoLocalEmail implements Synchronise_Base_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['local_email'] = 0;
        return $newUser;
    }
}
