<?php
/**
 * Hydrator for mail_handler.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Hydrator_NoMailHandler implements Synchronise_Base_IHydrator {

	/**
     * Hydrates user's mail_handler setting
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['mail_handler'] = 0;

        return $newUser;
    }
}
