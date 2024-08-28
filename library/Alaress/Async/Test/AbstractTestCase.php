<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Test;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Log\LoggerInterface;

abstract class AbstractTestCase extends BaseTestCase implements TestCase
{
    /** @var LoggerInterface */
    protected $log;

    /** @var callable */
    private $buffy;

    /**
     * @param LoggerInterface $log
     * @return void
     */
    public function attachLogger(LoggerInterface $log)
    {
        $this->log = $log;
    }


    /**
     * This test has finished. It is considered to have passed if no assertions failed during execution.
     *
     * @return void
     */
    protected function markTestComplete()
    {
        $this->log->notice('Test complete');
        $this->terminateDaemons();
    }


    /**
     * Install a mechanism to slay the daemons you invoked as part of the test
     *
     * @param callable $daemonSlayer
     * @return void
     */
    public function setBuffy(callable $daemonSlayer)
    {
        $this->buffy = $daemonSlayer;
    }


    /**
     * Kills the daemons IF a Buffy was installed
     *
     * @return void
     */
    protected function terminateDaemons()
    {
        if (is_callable($this->buffy)) {
            call_user_func($this->buffy);
        }
    }
}
