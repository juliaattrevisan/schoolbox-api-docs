<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Queue;

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\NotificationBundle;

/**
 * Composite queue: any action on this is replicated across all sub-queues.
 */
class Composite implements QueueInterface
{
    /**
     * @var QueueInterface[]
     */
    private $queues;

    /**
     * Constructor
     *
     * @param QueueInterface ...$queues
     */
    public function __construct(QueueInterface ...$queues)
    {
        $this->queues = $queues;
    }

    /**
     * Composite queues have no name.
     *
     * @return string
     */
    public function getName()
    {
        return '';
    }

    /**
     * Composite queue contains message if any sub-queue does.
     *
     * @param Instance $instance
     * @return bool
     */
    public function contains(Instance $instance)
    {
        foreach ($this->queues as $queue) {
            if ($queue->contains($instance)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function drain()
    {
        foreach ($this->queues as $queue) {
            $queue->drain();
        }
    }

    /**
     * @inheritdoc
     */
    public function push(Instance $instance)
    {
        foreach ($this->queues as $queue) {
            $queue->push($instance);
        }
    }

    /**
     * @inheritdoc
     */
    public function fetchIntoBundle(NotificationBundle $bundle, $dropCache = false)
    {
        foreach ($this->queues as $queue) {
            $queue->fetchIntoBundle($bundle, $dropCache);
        }
    }
}
