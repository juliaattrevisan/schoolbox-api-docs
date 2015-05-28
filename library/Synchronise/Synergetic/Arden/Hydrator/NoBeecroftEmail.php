<?php
/**
 * NoAltEmail
 *
 * Deliberately clear the alt_email field because Junior School teachers
 * don't want parents contacting them
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Synergetic_Arden_Hydrator_NoBeecroftEmail implements Synchronise_Base_IHydrator {

    /**
     * Deliberately remove alt_email value for Beecroft campus users
     *
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        if ($externalUser['StaffCampus'] == 'B') {
            $newUser['alt_email'] = '';
        }

        return $newUser;
    }
}