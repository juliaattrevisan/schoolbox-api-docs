<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\AMQP;


use PhpAmqpLib\Message\AMQPMessage;

/**
 * Interface AMQPResponseHandlerInterface
 *
 * @deprecated
 * @package Alaress\Async\AMQP
 */
interface AMQPResponseHandlerInterface
{

    /**
     * @deprecated
     *
     * Called from within a Subscription object for notifying attached sockets of a new message
     *
     * @param AMQPMessage $message
     * @param int         $userId
     * @return bool
     */
    public function onAMQP($message, $userId);
} 