<?php
/**
 * Hydrator for username for Toowoomba parents.
 *
 * Currently, this is the same as the Cathedral username sync.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Toowoomba_Hydrator_Username_Parent implements Synchronise_Base_IHydrator
{
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        $externalId = $externalUser['StudentContactID'];
        if ($externalUser['add_num'] == 4) {
            $externalId .= '-4';
        }
        $newUser['username'] = $externalId;
        return $newUser;
    }
}
