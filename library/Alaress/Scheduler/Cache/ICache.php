<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
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
    public function saveSchedule(ResolutionManager $resolvers);


    /**
     * @param Connector $mq
     * @return bool
     */
    public function restoreSchedule(Connector $mq);

}