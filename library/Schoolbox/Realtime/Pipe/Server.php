<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Pipe;

/**
 * Schoolbox WebSocket-specific override.
 *
 * If a connection cannot be handled, we do not want it to explode, we
 * just want the server to ignore it and carry on business as usual.
 *
 * @package Schoolbox\Realtime\Pipe
 */
class Server extends \React\Socket\Server
{
    /**
     * @param resource $socket
     */
    public function handleConnection($socket)
    {
        try {
            parent::handleConnection($socket);
        } catch (\ErrorException $e) {
            // suppressing fiiiiire!
        }

    }

}