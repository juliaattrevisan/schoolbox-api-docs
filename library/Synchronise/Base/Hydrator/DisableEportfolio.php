<?php
/**
 * Disable ePortfolio
 *
 * @author    James Leckie <jleckie@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Hydrator_DisableEportfolio implements Synchronise_Base_IHydrator {

	/**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['eportfolio_enabled'] = 0;

        return $newUser;
    }
}
