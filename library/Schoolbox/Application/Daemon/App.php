<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Application\Daemon;

use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;

abstract class App
{

    /**
     * @param Di\Container  $di
     * @param LoopInterface $loop
     * @param Logger        $log
     * @param array         $opts
     * @param string        $flags Space-separated list of command line options that were passed to the php script that invoked this app
     * @return void
     */
    abstract public function run(Di\Container $di, LoopInterface $loop, Logger $log, array $opts, string $flags): void;


    /**
     * @param Di\Container  $di
     * @param LoopInterface $loop
     * @param Logger        $log
     * @param array         $opts
     * @param string        $flags
     * @return void
     */
    public function __invoke(Di\Container $di, LoopInterface $loop, Logger $log, array $opts, string $flags): void
    {
        $this->run($di, $loop, $log, $opts, $flags);
    }
}
