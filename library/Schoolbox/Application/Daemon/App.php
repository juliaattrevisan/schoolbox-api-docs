<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
     * @return void
     */
    public abstract function run(Di\Container $di, LoopInterface $loop, Logger $log, array $opts);


    /**
     * @param Di\Container  $di
     * @param LoopInterface $loop
     * @param Logger        $log
     * @param array         $opts
     * @return void
     */
    public function __invoke(Di\Container $di, LoopInterface $loop, Logger $log, array $opts)
    {
        $this->run($di, $loop, $log, $opts);
    }
}