<?php
/**
 * Default values for SCS parents
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Scs_Hydrator_Parent implements Synchronise_Base_IHydrator
{
    /**
     * Switches ePortfolio and webmail off for parents by default.
     *
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        $newUser['webmail']            = false;
        $newUser['eportfolio_enabled'] = false;
        return $newUser;
    }
}
