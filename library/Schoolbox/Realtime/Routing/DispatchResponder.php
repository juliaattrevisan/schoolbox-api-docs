<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing;

use Alaress\WorkerCrew\TaskResponseHandlerInterface;
use Psr\Log\LoggerInterface;

class DispatchResponder implements TaskResponseHandlerInterface
{

    /** @var LoggerInterface */
    protected $log;


    /**
     * DispatchResponder constructor.
     * @param LoggerInterface $log
     */
    public function __construct(LoggerInterface $log)
    {
        $this->log = $log;
    }


    /**
     * Handles a response from a Worker
     *
     * @param object $identifier - the identifier passed as the first argument to Dispatcher::enqueue()
     * @param string $command    - the command that was executed by the Worker
     * @param array  $data       - the data with which the Worker responded
     * @return void              - this method does not return any value
     */
    public function handleResponse($identifier, $command, $data)
    {
        $this->log->notice("Reroute count: $data[0]");
    }


    /**
     * Handles an error from a Worker
     *
     * @param object $identifier - the identifier passed as the first argument to Dispatcher::enqueue()
     * @param string $command    - the command that was executed by the Worker
     * @param array  $data       - the data with which the Worker responded
     * @return void              - this method does not return any value
     */
    public function handleError($identifier, $command, $data)
    {
        $this->log->notice("Received worker error for '$command': $data");
    }
}
