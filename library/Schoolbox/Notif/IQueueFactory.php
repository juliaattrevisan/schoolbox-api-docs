<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif;


use Alaress\Async\UserQueue\UserQueue;

interface IQueueFactory
{

    /**
     * @param string $kind
     * @return self
     */
    public function setKind($kind);


    /**
     * @param $userId
     * @return UserQueue
     */
    public function buildForUser($userId);

}
