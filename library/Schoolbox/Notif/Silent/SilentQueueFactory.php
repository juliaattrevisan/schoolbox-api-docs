<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Silent;


use Alaress\Async\UserQueue\UserQueue;
use Schoolbox\Notif\IQueueFactory;

class SilentQueueFactory implements IQueueFactory
{

    /**
     * @param string $kind
     * @return self
     */
    public function setKind($kind)
    {
        return $this;
    }


    /**
     * @param $userId
     * @return UserQueue
     */
    public function buildForUser($userId)
    {
        return null;
    }

} 