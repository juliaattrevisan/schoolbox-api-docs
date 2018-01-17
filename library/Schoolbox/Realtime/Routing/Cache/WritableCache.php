<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Routing\Cache;

use UnderflowException;

interface WritableCache
{

    /**
     * @param string $instanceId
     * @param int[] $userIds
     * @return void
     */
    public function cacheUsers($instanceId, array $userIds);

    /**
     * @param string $instanceId
     * @return void
     */
    public function flushCacheUsersQueue($instanceId);

    /**
     * @param string $instanceId
     * @throws UnderflowException
     * @return void
     */
    public function cacheAllUsers($instanceId);

    /**
     * @param string $instanceId
     * @return void
     */
    public function cacheRedirects($instanceId);
}
