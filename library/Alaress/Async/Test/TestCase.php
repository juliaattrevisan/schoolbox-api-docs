<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Async\Test;


use Psr\Log\LoggerInterface;

interface TestCase
{

    const DAEMON_SCHEDULE = 'scheduled';
    const DAEMON_REROUTE  = 'rerouted';
    const DAEMON_SOCKET   = 'socketd';
    const DAEMON_DIGEST   = 'digestd';
    const DAEMON_BELL     = 'belld';

    /**
     * Provide the list of daemons that need to be attached for this test
     *
     * @return array
     */
    public function requiredDaemons();


    /**
     * @param LoggerInterface $log
     * @return void
     */
    public function attachLogger(LoggerInterface $log);

}
