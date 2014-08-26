<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Queue\Builder;


use Alaress\Async\Queue\RedisQueue;
use Predis\ClientInterface;
use Schoolbox\User;

class RedisBuilder implements BuilderInterface
{

    const STORE = 'redis';

    /** @var ClientInterface */
    protected $redis;


    /**
     * @param ClientInterface $redis
     */
    public function __construct(ClientInterface $redis)
    {
        $this->redis = $redis;
    }


    /**
     * @param string $label
     * @param int    $id
     * @return string
     */
    public function createQueueName($label, $id)
    {
        return 'q:' . INSTANCE_ID . ":$id:$label";
    }


    /**
     * @param string $label
     * @param User   $user
     * @return RedisQueue
     */
    public function createQueue($label, $user)
    {
        return new RedisQueue($this->redis, $this->createQueueName($label, $user->getId()));
    }


    /**
     * @param Plan $plan
     * @param User $user
     * @return RedisQueue[]
     */
    public function generateQueues(Plan $plan, User $user)
    {
        $queues = array();
        if (!$plan->containsIntention(self::STORE)) {
            return $queues;
        }

        foreach (array_keys($plan->getIntention(self::STORE)) as $label) {
            $queues[] = $this->createQueue($label, $user);
        }

        return $queues;
    }


    /**
     * @param Plan $plan
     * @param int  $userId
     * @return RedisQueue[]
     */
    public function generateQueueNames(Plan $plan, $userId)
    {
        $queues = array();
        if (!$plan->containsIntention(self::STORE)) {
            return $queues;
        }

        foreach (array_keys($plan->getIntention(self::STORE)) as $label) {
            $queues[] = $this->createQueueName($label, $userId);
        }

        return $queues;
    }

}