<?php
/**
 * Clears all fields (thus stopping sync) on create.
 *
 * Most useful for parents sync, as many schools don't create parent accounts.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Hydrator_AllowUpdateOnly extends Synchronise_Base_Hydrator_CreateUpdate
{
    /**
     * {@inheritdoc}
     */
    protected function hydrateOnCreate($externalUser, $newUser)
    {
        // return a campus array at least to avoid warning later
        return array(
            'campus' => array()
        );
    }
}
