<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Pipe;

/**
 * Interface PipeInterface
 *
 * @package Schoolbox\Realtime\Pipe
 */
interface PipeInterface
{

    /**
     * @return void
     */
    public function close();

    /**
     * @return string
     */
    public function getRemoteAddress();

    /**
     * @return string
     */
    public function getSession();

    /**
     * @return string
     */
    public function getSocketKey();


    /**
     * @return int
     */
    public function getUserId();

    /**
     * @param int $id
     */
    public function setUserId($id);

    /**
     * @param string $data
     * @return self
     */
    public function send($data);

    /**
     * Prevent additional commands being executed by this pipe until it releases
     *
     * @param string $command
     * @return void
     */
    public function lock($command);

    /**
     * Is this pipe currently locked?
     *
     * @return bool
     */
    public function isLocked();

    /**
     * Release the command lock on this pipe
     *
     * @return void
     */
    public function unlock();
}
