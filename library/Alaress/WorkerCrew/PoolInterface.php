<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\WorkerCrew;

use Alaress\WorkerCrew\Worker\Task;
use Evenement\EventEmitterInterface;
use OutOfBoundsException;

interface PoolInterface extends EventEmitterInterface
{

    const RELEASE_HARD = true;
    const RELEASE_SOFT = false;


    /**
     * Returns the PID of the process associated with the given stream or socket
     * Throws OutOfBounds exception if no worker matches the resource
     *
     * @param resource $resource
     * @throws OutOfBoundsException
     * @return int
     */
    public function getProcessIdByResource($resource);


    /**
     * Request to release the worker with the given PID, either gracefully or forcefully.
     * The implementing class may elect to ignore this.
     * Will throw OutOfBoundsException if no Worker with the given PID is found.
     *
     * @param int  $pid
     * @param bool $hard
     * @throws OutOfBoundsException
     * @return void
     */
    public function releaseWorker($pid, $hard = self::RELEASE_HARD);


    /**
     * Returns the Task currently assigned to the worker with the given PID.
     * Will throw OutOfBoundsException if no Worker with the given PID is found.
     *
     * @param int $pid
     * @throws OutOfBoundsException
     * @return Task
     */
    public function getCurrentTaskByProcessId($pid);


    /**
     * Request to set the status of the worker with the given PID.
     * The implementing class may elect to ignore this.
     * Will throw OutOfBoundsException if no Worker with the given PID is found.
     * Returns the (possibly updated) status of the worker
     *
     * @param int $pid
     * @param int $status
     * @throws OutOfBoundsException
     * @return int
     */
    public function setWorkerStatus($pid, $status);


    /**
     * Calculates the worker state based on the Event supplied.
     *
     * @param string $data
     * @return int
     */
    public function calculateState($data);


    /**
     * Returns whether or not the implementing class is able to accept a new task
     *
     * @return bool
     */
    public function canAccept();
}
