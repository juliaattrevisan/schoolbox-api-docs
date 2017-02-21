<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Async\Test;


use PHPUnit_Framework_TestCase;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

abstract class AbstractTest extends PHPUnit_Framework_TestCase implements TestCase
{

    /** @var LoggerInterface */
    protected $log;

    /** @var LoopInterface */
    private $loop;

    /** @var callable */
    private $buffy;


    /**
     * @param LoopInterface $loop
     */
    public function attachLoop(LoopInterface $loop)
    {
        $this->loop = $loop;
    }
    
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
        $this->log->notice("Test complete");
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
