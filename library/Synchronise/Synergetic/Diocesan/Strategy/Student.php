<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Synchronise\Synergetic\Diocesan\Strategy;


use Synchronise\Synergetic\Diocesan;
use Synchronise_Base_Strategy_Student;

class Student extends Synchronise_Base_Strategy_Student
{

    /**
     * Setup verbose output and hydration classes
     */
    protected function setup()
    {
        $this->setVerboseFilter(new Diocesan\Verbose());

        $this
            ->addHydrator(new \Synchronise_Base_Hydrator_Default())

            ->addHydrator(new Diocesan\Hydrator\Student())
            ->addHydrator(new Diocesan\Hydrator\House())
            ->addHydrator(new Diocesan\Hydrator\Eportfolio())

            ->addHydrator(new \Synchronise_Base_Hydrator_LocalEmail())
            ->addHydrator(new \Synchronise_Base_Hydrator_Webmail())
            ->addHydrator(new Diocesan\Hydrator\UnsetOnUpdate())
            ->addHydrator(new \Synchronise_Base_Hydrator_Clear());
    }

}
