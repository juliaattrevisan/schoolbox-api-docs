<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Scheduler;

interface CommandableInterface
{

    /**
     * Bind the queue(s) relevant to your commands
     *
     * @return void
     */
    public static function bindQueues();
}
