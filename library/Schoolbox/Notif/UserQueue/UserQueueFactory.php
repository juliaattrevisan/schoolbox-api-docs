<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\UserQueue;


use Alaress\Async\UserQueue\UserQueue;
use Schoolbox\Notif\AbstractQueueFactory;
use UnexpectedValueException;

class UserQueueFactory extends AbstractQueueFactory
{
    const UQ_DIGEST  = 1;
    const UQ_INSTANT = 2;
    /** @deprecated */
    const UQ_TO_SORT = 3;
    const UQ_REROUTE = 4;

    /**
     * @param int $userId
     * @throws UnexpectedValueException
     * @return UserQueue
     */
    public function buildForUser($userId)
    {
        switch ($this->kind) {

            case self::UQ_DIGEST:
                return new DigestUserQueue($this->getRedis(), $userId);

            case self::UQ_INSTANT:
                return new InstantUserQueue($this->getRedis(), $this->getAMQP(), $userId);

            case self::UQ_TO_SORT:
                return new ToSortUserQueue($this->getAMQP(), $userId);

            case self::UQ_REROUTE:
                return new RerouteUserQueue($this->getRedis(), $userId);

        }
        throw new UnexpectedValueException("Unknown queue '$this->kind'");
    }

}