<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Alert;

use Alaress\Async\Notification\Instance\Instance;

interface IPublisher
{
    /**
     * Publishes an alert to the given user. Returns the number of recipients
     * successfully alerted (return values other than 0 or 1 would be...unexpected)
     *
     * @param int      $userId
     * @param Instance $instance
     * @return int
     */
    public function alertUser($userId, Instance $instance);

}