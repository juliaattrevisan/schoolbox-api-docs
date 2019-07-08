<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Synchronise\Synergetic\Diocesan\Strategy;


use Synchronise_Base_Strategy_Student;
use Synchronise\Synergetic\Diocesan;

class Guest extends Synchronise_Base_Strategy_Student
{
    /** @var string */
    protected $title = "Update Schoolbox Guest Details Synchronisation";

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
