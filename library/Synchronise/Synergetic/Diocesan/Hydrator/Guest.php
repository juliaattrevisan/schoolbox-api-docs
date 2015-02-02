<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Synchronise\Synergetic\Diocesan\Hydrator;


use Synchronise_Base_IHydrator;

class Guest implements Synchronise_Base_IHydrator
{
    const ROLE_OLD_GIRLS = 17;

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['username']             = isset($externalUser['NetworkLogin']) ? $externalUser['NetworkLogin'] : '';
        $newUser['title']                = $externalUser['StudentTitle'];
        $newUser['firstname']            = $externalUser['StudentPreferred'];
        $newUser['lastname']             = $externalUser['StudentSurname'];
        $newUser['synergy_id']           = $externalUser['StudentID'];
        $newUser['alt_email']            = null;
        $newUser['local_email']          = null;
        $newUser['webmail']              = null;
        $newUser['year']                 = null;
        $newUser['defaultCampus']        = null;
        $newUser['campus']               = array();
        $newUser['group_id']             = self::ROLE_OLD_GIRLS;
        $newUser['su']                   = false;
        $newUser['house']                = null;
        $newUser['residential_house_id'] = null;
        $newUser['eportfolio_enabled']   = false;
        $newUser['enabled']              = null;

        if (strlen(trim($externalUser['StudentOccupEmail']))) {
            $newUser['alt_email'] = $externalUser['StudentOccupEmail'];
        } else {
            $newUser['alt_email'] = '';
        }

        return $newUser;
    }

} 