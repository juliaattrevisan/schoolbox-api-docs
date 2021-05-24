<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\WorkerCrew\Worker;

use OutOfBoundsException;

interface WorkerBundleInterface
{

    /**
     * Attaches a Worker
     *
     * @param int    $pid
     * @param Worker $worker
     */
    public function attach($pid, $worker);


    /**
     * Detaches a Worker given its PID
     *
     * @param int $pid
     */
    public function detach($pid);


    /**
     * Detaches Workers given a bitwise status grouping
     *
     * @param int $status
     */
    public function detachWhere($status);


    /**
     * Returns whether or not a Worker is attached with the given PID
     *
     * @param int $pid
     * @return bool
     */
    public function contains($pid);


    /**
     * Returns the Worker with the given PID
     * Throws OutOfBounds exception if it does not exist
     *
     * @param int $pid
     * @throws OutOfBoundsException
     * @return Worker
     */
    public function getByProcessId($pid);


    /**
     * Returns the PID of the worker attached to the given stream (each Worker will have 4)
     * Throws OutOfBounds exception if it does not exist
     *
     * @param resource $resource
     * @throws OutOfBoundsException
     * @return int
     */
    public function getProcessIdByResource($resource);


    /**
     * Returns a (single) Worker that matches the given status bitwise grouping
     * It's up to the implementing class how to decide which Worker
     * Throws OutOfBounds exception if no matching workers are found
     *
     * @param int $status
     * @throws OutOfBoundsException
     * @return Worker
     */
    public function getWorkerWhere($status);


    /**
     * Returns the PID of a (single) Worker that matches the given status bitwise grouping
     * It's up to the implementing class how to decide which Worker
     * Throws OutOfBounds exception if no matching workers are found
     *
     * @param int $status
     * @throws OutOfBoundsException
     * @return int
     */
    public function getPidWhere($status);


    /**
     * Returns the number of Workers this bundle contains
     *
     * @return int
     */
    public function count();


    /**
     * Returns the number of Workers that match the given bitwise status grouping
     *
     * @param int $status
     * @return int
     */
    public function countWhere($status);


    /**
     * Return the status of the workers
     *
     * @return array
     */
    public function getStatus();


    /**
     * Unceremoniously terminate a worker
     *
     * @param int $pid
     */
    public function terminateProcess($pid);


    /**
     * Unceremoniously terminate all workers
     *
     * @return void
     */
    public function terminateAll();


    /**
     * Allow worker to finish what it's doing, then die
     *
     * @param int $pid
     */
    public function releaseProcess($pid);


    /**
     * Allow all workers to finish what they're doing, then die
     */
    public function releaseAll();


    /**
     * Send the given signal to the given worker
     *
     * @param int $pid
     * @param int $signal
     */
    public function signalProcess($pid, $signal);


    /**
     * Signal all workers
     *
     * @param int $signal
     * @return void
     */
    public function signalAll($signal);
}
