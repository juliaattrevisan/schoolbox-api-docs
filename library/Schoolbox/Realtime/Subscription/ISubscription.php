<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Subscription;

interface ISubscription
{

    /**
     * @return void
     */
    public function unsubscribe();

    /**
     * @return string
     */
    public function getRouteKey();

    /**
     * @param string $messageId
     * @return bool
     */
    public function acknowledgeMessage($messageId);

}