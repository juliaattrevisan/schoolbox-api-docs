<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Synchronise\Synergetic\Diocesan\Hydrator;


use Synchronise_Base_Hydrator_UnsetOnUpdate;

class UnsetOnUpdate extends Synchronise_Base_Hydrator_UnsetOnUpdate
{
    /** @var array */
    protected $clearFields = array('su', 'enabled');

    /**
     * {@inheritdoc}
     */
    protected function hydrateOnUpdate($externalUser, $newUser)
    {
        foreach ($this->getClearFields() as $clearField) {
            unset($newUser[$clearField]);
        }

        /* Don't change non teacher staff campus */
        if($externalUser['StaffCategoryType'] != 'TEACHER') {
            unset($newUser['campus']);
            unset($newUser['defaultCampus']);
        }

        return $newUser;
    }

} 