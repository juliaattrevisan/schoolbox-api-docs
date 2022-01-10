<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Test;

use Psr\Log\LoggerInterface;

interface TestCase
{

    const DAEMON_SCHEDULE = 'scheduled';
    const DAEMON_ROUTE    = 'routed';
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
     * Perform any setup required
     *
     * @return void
     */
    public function prepare();

    /**
     * @param LoggerInterface $log
     * @return void
     */
    public function attachLogger(LoggerInterface $log);

    /**
     * Install a mechanism to slay the daemons you invoked as part of the test
     *
     * @param callable $daemonSlayer
     * @return void
     */
    public function setBuffy(callable $daemonSlayer);
}
