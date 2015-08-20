<?php
/**
 * Skip parent records that aren't:
 * * PAM (Mother)
 * * PAF (Father)
 * * REL (Guardian)
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Synchronise_External_Cis_Hydrator_Parent_SkipRelationships implements Synchronise_Base_IHydrator
{
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        switch ($externalUser['StudentContactType']) {
            case 'PAM':
            case 'PAF':
            case 'REL':
                return $newUser;
        }
        throw new Exception('Only parents or guardians are synced');
    }
}
