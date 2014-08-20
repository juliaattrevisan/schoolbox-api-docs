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
     * Yes I know this is the wrong way around, that's getting fixed in the
     * Cathedral sync.
     *
     * {@inheritdoc}
     */
    protected function hydrateOnUpdate($externalUser, $newUser)
    {
        return array();
    }
}
