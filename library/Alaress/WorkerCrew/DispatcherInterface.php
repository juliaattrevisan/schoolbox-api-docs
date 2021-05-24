<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\WorkerCrew;

interface DispatcherInterface
{

    /**
     * Accepts a task to be dispatched.
     *
     * @param object $identifier
     * @param string $command
     * @param string $data
     */
    public function enqueue($identifier, $command, $data);

    /**
     * Returns the current status of the worker pool
     *
     * @return array
     */
    public function getPoolStatus();


    /**
     * Signals that all workers should be terminated
     *
     * @param bool $hard
     * @return void
     */
    public function releaseWorkerPool($hard = true);
}
