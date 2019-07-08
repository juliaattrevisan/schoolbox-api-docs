<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing\Cache;

use Alaress\Routing\IRouter;


interface ReadableCache
{
    /**
     * @param string  $instanceId
     * @param IRouter $router
     * @return void
     */
    public function readIntoRouter($instanceId, IRouter $router);

}
