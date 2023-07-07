<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Scheduler\Cache;

use Alaress\Scheduler\Stomp\ResolutionManager;
use Schoolbox\Notif\MQ\Connector;

interface ICache
{
    /**
     * Save the schedule held by the given ResolutionManager
     *
     * @param ResolutionManager $resolvers
     * @return bool
     */
    public function saveSchedule(ResolutionManager $resolvers): bool;


    /**
     * @param Connector   $mq
     * @param string      $queueName
     * @return bool
     */
    public function restoreSchedule(Connector $mq, string $queueName): bool;
}
