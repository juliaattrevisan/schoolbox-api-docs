<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\MessageList\Mobile;

use Alaress\Async\Broker\IBroker;
use Alaress\Async\Queue\QueueInterface;

/**
 * Factory for building mobile queues which forward to a brokered queue.
 */
class ForwardToBrokeredQueueFactory implements IQueueFactory
{
    /**
     * Constructor
     *
     * @param IBroker $broker     The brokered queue to publish into.
     * @param string  $instanceId The identifier for this Schoolbox instance.
     */
    public function __construct(private IBroker $broker, private string $instanceId)
    {
    }

    /**
     * @inheritdoc
     */
    public function build(int $userId): QueueInterface
    {
        return new ForwardToBrokeredQueue($this->broker, $this->instanceId, $userId);
    }
}
