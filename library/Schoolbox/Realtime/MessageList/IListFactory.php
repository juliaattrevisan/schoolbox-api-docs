<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\MessageList;

use Alaress\Async\Queue\QueueInterface;


interface IListFactory
{

    /**
     * @param int $userId
     * @return QueueInterface
     */
    public function listed($userId);


    /**
     * @param int $userId
     * @return QueueInterface
     */
    public function unread($userId);


    /**
     * @param int $userId
     * @return QueueInterface
     */
    public function digest($userId);

}