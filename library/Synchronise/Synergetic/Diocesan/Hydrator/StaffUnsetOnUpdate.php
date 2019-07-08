<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Synchronise\Synergetic\Diocesan\Hydrator;


use Synchronise_Base_Hydrator_UnsetOnUpdate;

class StaffUnsetOnUpdate extends Synchronise_Base_Hydrator_UnsetOnUpdate
{
    /**
     * {@inheritdoc}
     */
    protected function hydrateOnUpdate($externalUser, $newUser)
    {
        /* Don't change non teacher staff campus */
        if ($externalUser['StaffCategory'] != 'TEACH') {
            unset($newUser['campus']);
            unset($newUser['defaultCampus']);
        }

        return $newUser;
    }

} 
