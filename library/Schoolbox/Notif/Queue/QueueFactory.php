<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Queue;


use Alaress\Async\Queue\QueueInterface;
use Alaress\Async\UserQueue\UserQueue;
use Schoolbox\Notif\AbstractQueueFactory;
use UnexpectedValueException;

class QueueFactory extends AbstractQueueFactory
{

    /**
     * @param int $userId
     * @throws UnexpectedValueException
     * @return QueueInterface
     */
    public function buildForUser($userId)
    {
        switch ($this->kind) {

            case UserQueue::Q_LISTED:
                $this->getRedis();
                return new ListedQueue($this->getRedis(), $userId);

            case UserQueue::Q_DIGEST:
                $this->getRedis();
                return new DigestQueue($this->getRedis(), $userId);

            case UserQueue::Q_UNREAD:
                $this->getAMQP();
                // TODO replace with REDIS
                return new UnreadQueue($this->getAMQP(),  $userId);

//            case UserQueue::Q_TOSORT:
//                $this->getAMQP();
//                return new ToSortQueue($this->getAMQP(),  $userId);

        }
        throw new UnexpectedValueException("Unknown queue '$this->kind'");
    }

} 