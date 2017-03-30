<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Routing\Cache;

use Alaress\Routing\IRouter;


interface ICache
{

    /**
     * @param string $instanceId
     * @param int $userId
     * @return void
     */
    public function cacheUser($instanceId, $userId);

    /**
     * @param string $instanceId
     * @return void
     */
    public function cacheAllUsers($instanceId);


    /**
     * @param string  $instanceId
     * @param IRouter $router
     * @return void
     */
    public function readIntoRouter($instanceId, IRouter $router);

}