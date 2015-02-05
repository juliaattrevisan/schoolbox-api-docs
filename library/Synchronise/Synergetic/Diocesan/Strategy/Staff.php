<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Synchronise\Synergetic\Diocesan\Strategy;


use Synchronise_Base_Strategy_Staff;

use Synchronise\Synergetic\Diocesan;

class Staff extends Synchronise_Base_Strategy_Staff
{

    /**
     * Setup verbose output and hydration classes
     */
    protected function setup()
    {
        $this->setVerboseFilter(new Diocesan\Verbose());

        $this
            ->addHydrator(new \Synchronise_Base_Hydrator_Default())
            ->addHydrator(new \Synchronise_Base_Hydrator_NoEportfolio())

            ->addHydrator(new Diocesan\Hydrator\Staff(new \External_User()))

            ->addHydrator(new \Synchronise_Base_Hydrator_LocalEmail())
            ->addHydrator(new \Synchronise_Base_Hydrator_Webmail())

            ->addHydrator(new Diocesan\Hydrator\UnsetOnUpdate())
            ->addHydrator(new \Synchronise_Base_Hydrator_Clear());
    }

}
