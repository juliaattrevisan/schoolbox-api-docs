<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Pipe;

use Ratchet\ConnectionInterface;

/**
 * Schoolbox WebSocket-specific override.
 *
 * Do not attempt to respond to closing sockets.
 *
 * @package Schoolbox\Realtime\Pipe
 */
class HttpServer extends \Ratchet\Http\HttpServer
{
    /**
     * Close a connection with an HTTP response
     *
     * @param ConnectionInterface $conn
     * @param int                 $code HTTP status code
     * @return void
     */
    protected function close(ConnectionInterface $conn, $code = 400)
    {
        // say nothing
        $conn->close();
    }
}
