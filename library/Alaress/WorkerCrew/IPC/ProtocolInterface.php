<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\WorkerCrew\IPC;

interface ProtocolInterface
{


    /**
     * @return int
     */
    public function chunkLength();

    /**
     * @param string $command
     * @param array $args
     * @return string
     */
    public function prepareCommand($command, $args = null);

    /**
     * Prepare an array of elements for transmission
     *
     * @param array $array
     * @return string
     */
    public function flatten($array);

    /**
     * @param string $string
     * @return array
     */
    public function rebuild($string);

    /**
     * @return string
     */
    public function reject();

    /**
     * @return string
     */
    public function accept();

    /**
     * @return string
     */
    public function cancel();

    /**
     * @return string
     */
    public function end();

    /**
     * @param string $data
     * @return bool
     */
    public function isCancelled($data);

    /**
     * @param string $data
     * @return bool
     */
    public function isEnded($data);

    /**
     * @param string $data
     * @return bool
     */
    public function isRejected($data);

    /**
     * @param string $data
     * @return bool
     */
    public function isAccepted($data);
}
