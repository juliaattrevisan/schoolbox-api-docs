<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Synchronise\Synergetic\Diocesan\Strategy;


use Synchronise_Base_Strategy_Student;
use Synchronise\Synergetic\Diocesan;

class Guest extends Synchronise_Base_Strategy_Student
{
    /**
     * Setup verbose output and hydration classes
     */
    protected function setup()
    {
        $this->setVerboseFilter(new Diocesan\Verbose());

        $this
            ->addHydrator(new Diocesan\Hydrator\Guest())
            ->addHydrator(new \Synchronise_Base_Hydrator_MailHandler())
        ;
    }

}
