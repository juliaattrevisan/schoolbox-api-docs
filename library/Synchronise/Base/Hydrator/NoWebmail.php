<?php
/**
 * Hydrator for Webmail
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class Synchronise_Base_Hydrator_NoWebmail implements Synchronise_Base_IHydrator {

	/**
     * Hydrates user's webmail setting
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['webmail'] = 0;

        return $newUser;
    }
}
