<?php
/**
 * Disable eportfolio
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class Synchronise_Base_Hydrator_NoEportfolio implements Synchronise_Base_IHydrator {

	/**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['eportfolio_enabled'] = 0;

        return $newUser;
    }
}
