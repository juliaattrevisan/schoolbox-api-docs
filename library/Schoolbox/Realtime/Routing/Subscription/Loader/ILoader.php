<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing\Subscription\Loader;

use Alaress\Routing\IRouter;
use Traversable;

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


    /**
     * @param int $userId
     * @return array
     */
    public function getUserSubscriptions($userId): array;

    /**
     * @return Traversable
     */
    public function getRedirects();
}
