<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Synchronise\Synergetic\Diocesan\Strategy;


use Synchronise_Base_Strategy_Parent;

use Synchronise\Synergetic\Diocesan;

/**
 * Parents, because Parent is a reserved keyword -_-
 */
class Parents extends Synchronise_Base_Strategy_Parent
{
    /**
     * Setup verbose output and hydration classes
     */
    protected function setup()
    {
        $this->setVerboseFilter(new Diocesan\Verbose());

        $this
            ->addHydrator(new \Synchronise_Base_Hydrator_Default())

            ->addHydrator(new Diocesan\Hydrator\Parents())


            ->addHydrator(new \Synchronise_Base_Hydrator_MailHandler())
            ->addHydrator(new \Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate())
            ->addHydrator(new \Synchronise_Base_Hydrator_Clear());
    }

}
