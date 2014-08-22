<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Queue\Builder;


use Alaress\Async\Queue\QueueInterface;
use OutOfBoundsException;
use Schoolbox\User;

class Plan
{

    /** @var BuilderInterface[] */
    protected $builders = array();

    /** @var array */
    protected $intent;


    /**
     * @param string          $store
     * @param BuilderInterface $builder
     */
    public function setBuilder($store, BuilderInterface $builder)
    {
        $this->builders[$store] = $builder;
    }


    /**
     * @param string $store
     * @return BuilderInterface
     * @throws OutOfBoundsException
     */
    public function getBuilder($store)
    {
        if (empty($this->builders[$store])) {
            throw new OutOfBoundsException("No builder for store '$store' declared");
        }
        return $this->builders[$store];
    }


    /**
     * @param string $store
     * @param string $label
     * @param mixed  $data
     *
     */
    public function addIntention($store, $label, $data = null)
    {
        $this->intent[$store][$label] = $data;
    }


    /**
     * Does this plan contain any queues for this particular store?
     *
     * @param string $store
     * @return bool
     */
    public function containsIntention($store)
    {
        return isset($this->intent[$store]);
    }


    /**
     * @param string $store
     * @return array
     * @throws \OutOfBoundsException
     */
    public function getIntention($store)
    {
        if($this->containsIntention($store))
        {
            return $this->intent[$store];
        }
        throw new OutOfBoundsException("No plans for '$store' registered");
    }

    /**
     * Execute the Plan!
     *
     * @param User $user
     * @return QueueInterface[]
     */
    public function generateQueues(User $user)
    {
        $queues = array();

        foreach($this->builders as $builder)
        {
            $queues += $builder->generateQueues($this, $user);
        }

        return $queues;
    }


    /**
     * Execute the Plan! But differently!
     *
     * @param int $userId
     * @return array
     */
    public function generateQueueNames($userId)
    {
        $queues = array();

        foreach($this->builders as $builder)
        {
            $queues += $builder->generateQueueNames($this, $userId);
        }

        return $queues;
    }

}