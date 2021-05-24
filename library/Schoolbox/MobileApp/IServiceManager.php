<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\MobileApp;

use Alaress\Async\Notification\Instance\Instance;

interface IServiceManager
{

    /**
     * @param int      $userId
     * @param Instance $instance
     * @return void
     */
    public function push($userId, Instance $instance);
}
