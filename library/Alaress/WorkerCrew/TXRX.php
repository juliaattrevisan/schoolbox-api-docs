<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\WorkerCrew;

use Alaress\WorkerCrew\Transaction\ITransaction;
use BadMethodCallException;
use InvalidArgumentException;

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
     * @param mixed        $identifier
     * @param ITransaction $tx
     * @param mixed        $payload
     * @return void
     * @throws BadMethodCallException   if not correctly configured. May happen
     *                                  if this object was constructed to allow
     *                                  receipt only.
     * @throws InvalidArgumentException if the payload is in an unacceptable
     *                                  format.
     */
    public function send($identifier, ITransaction $tx, $payload);

    /**
     * EXECUTED WORKER-SIDE
     * Receive and process the work sent from the daemon
     *
     * @param array $args
     * @return int
     * @throws BadMethodCallException   if not correctly configured. May happen
     *                                  if this object was constructed to allow
     *                                  sending only.
     * @throws InvalidArgumentException if args contains something that's not
     *                                  acceptable.
     */
    public function receive(array $args);
}
