<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule;

use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use RuntimeException;
use Schoolbox\Mapper;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Schedule\Persistence\QueueItem;
use Schoolbox\Realtime\Schedule\Persistence\QueueItemMapper;
use SplQueue;

/**
 * The only difference between this strategy and the default is that it persists to the database.
 *
 * Class PoolingStrategy
 * @package Schoolbox\Realtime\Schedule
 */
class SchedulerDispatchStrategy extends DefaultDispatchStrategy
{

    /** @var QueueItemMapper */
    protected $mapper;

    /** @var Notif */
    protected $notifier;


    /**
     * @param Notif  $notif
     * @param Mapper $mapper
     * @param string  $flags
     */
    public function __construct($notif, $mapper, $flags = '')
    {
        parent::__construct($flags);
        $this->mapper   = $mapper;
        $this->notifier = $notif;
    }


    /**
     * Get the name of the strategy currently being used
     *
     * @return string
     */
    public function getName()
    {
        return 'Schedule';
    }


    /**
     * Constructs the queue, but also checks for any SQL-rescued events from AMQP being offline
     *
     * @return SplQueue
     */
    public function buildQueue()
    {
        try {
            /** @var QueueItem[] $qiColl */
            $qiColl = $this->mapper->findAll();

            foreach ($qiColl as $qi) {
                $event = $qi->getIdentifier();
                $command = $qi->getCommand();

                if ($event instanceof Event) {
                    if ($this->notifier->setTopic($command)->notice(serialize($event))) {
                        $this->mapper->delete($qi);
                    }
                }
            }
        } catch (RuntimeException $e) {
            // couldn't unserialize or rabbit offline, just leave it for now
        }

        return parent::buildQueue();
    }
}
