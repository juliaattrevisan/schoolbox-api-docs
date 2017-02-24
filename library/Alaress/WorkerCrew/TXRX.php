<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\WorkerCrew;

use Alaress\WorkerCrew\Transaction\ITransaction;
use BadMethodCallException;
use InvalidArgumentException;
use React\Stomp\Protocol\Frame;

/**
 * Transmit and Receive
 *
 * Interface for creating a cohesive strategy for sending/receiving work to/by workers
 *
 * @package Alaress\WorkerCrew
 */
interface TXRX
{
    /**
     * EXECUTED DAEMON-SIDE
     * Send the work from the daemon to the worker
     *
     * @param Frame        $frame
     * @param ITransaction $tx
     * @param mixed        $payload
     * @throws InvalidArgumentException if the payload is in an unacceptable format
     * @return void
     */
    public function send(Frame $frame, ITransaction $tx, $payload);

    /**
     * EXECUTED WORKER-SIDE
     * Receive and process the work sent from the daemon
     *
     * @param array $args
     * @throws BadMethodCallException if not correctly configured
     * @return int
     */
    public function receive(array $args);

}
