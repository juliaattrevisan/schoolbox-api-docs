<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Routing\Subscription\Loader;

use Alaress\Routing\IRouter;

/**
 * Decorator pattern (I *think*)
 *
 * @package Schoolbox\Realtime\Routing\Subscription\Loader
 */
interface ILoader
{
    /**
     * Load the subscription list into the given Router
     *
     * @param IRouter $router
     * @return $this
     */
    public function loadSubscriptions(IRouter $router);
}