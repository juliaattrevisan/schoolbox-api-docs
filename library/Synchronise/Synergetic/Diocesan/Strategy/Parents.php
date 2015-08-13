<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Synchronise\Synergetic\Diocesan\Strategy;


use External_User;
use Schoolbox\UserMapper;
use Synchronise_Base_Hydrator_Clear;
use Synchronise_Base_Hydrator_Default;
use Synchronise_Base_Hydrator_MailHandler;
use Synchronise_Base_Hydrator_NoWebmail;
use Synchronise_Base_Hydrator_Parent;
use Synchronise_Base_Hydrator_Spouse;
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
            ->addHydrator(new Synchronise_Base_Hydrator_Default())
            ->addHydrator(new \Synchronise_Base_Hydrator_NoEportfolio())

            ->addHydrator(new Diocesan\Hydrator\Parents(new Synchronise_Base_Hydrator_Parent(), new External_User(), UserMapper::getInstance()))
            ->addSpouseHydrator(new Diocesan\Hydrator\Parents(new Synchronise_Base_Hydrator_Spouse(), new External_User(), UserMapper::getInstance()))
            ->addHydrator(new Synchronise_Base_Hydrator_MailHandler())
            ->addHydrator(new Synchronise_Base_Hydrator_NoWebmail())
            ->addHydrator(new Diocesan\Hydrator\UnsetOnUpdate())
            ->addHydrator(new Synchronise_Base_Hydrator_Clear());
    }

}
