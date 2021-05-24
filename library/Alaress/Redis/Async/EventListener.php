<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Redis\Async;

use Alaress\Redis\Async\Event\IEvent;

interface EventListener
{

    /**
     * Handle the given event
     *
     * @param IEvent $event
     * @return void
     */
    public function handle(IEvent $event);
}
