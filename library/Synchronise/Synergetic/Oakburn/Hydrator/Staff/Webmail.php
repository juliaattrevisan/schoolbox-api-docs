<?php
/**
 * Hydrator for Webmail
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Oakburn_Hydrator_Staff_Webmail implements Synchronise_Base_IHydrator {

	/**
     * Hydrates user's webmail setting
	 *
	 * SOC have a special conditional webmail
     */
    public function hydrate($externalUser, $newUser) {

		/* This is supposed to be a conditional, but assume that everyone
		 * has webmail for the time being - nobody provided any useful details
		 * on the role mappings for this to take effect
		 */
		$newUser['webmail'] = 1;

        return $newUser;
    }
}
