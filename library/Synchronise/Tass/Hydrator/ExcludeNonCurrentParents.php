<?php
/**
 * Hydrator for excluding users who have a termination date.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Hydrator_ExcludeNonCurrentParents implements Synchronise_Base_IHydrator
{

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        if (strtoupper($externalUser['status_flg']) == 'N') {
            throw new Exception('Parents who are not current are not synced');
        }
    }
}
