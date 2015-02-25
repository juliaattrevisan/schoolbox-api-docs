<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule\Trigger\Test;


use Schoolbox\Realtime\Schedule\Trigger\DigestTrigger;
use Schoolbox\Role;

class MockDigestTrigger extends DigestTrigger
{

    /** @var int */
    protected $time;

    /** @var int */
    protected $interval;


    /**
     * @param string $schedule
     * @param Role   $role
     * @param int    $interval
     */
    function __construct($schedule, $role, $interval = 600)
    {
        parent::__construct($schedule, $role);
        $this->interval = $interval;
    }


    /**
     * OVERRIDE: always returns n minutes from now
     *
     * @return int
     */
    public function getTimestamp()
    {
        // every n minutes
        $this->time = time() + $this->interval;

        if (!isset($this->time)) {
            // but first iteration fires immediately
            return time();
        }
        return $this->time;
    }

} 