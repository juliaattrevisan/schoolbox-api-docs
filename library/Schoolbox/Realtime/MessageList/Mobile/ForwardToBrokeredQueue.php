<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\MessageList\Mobile;

use Alaress\Async\Broker\IBroker;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\NotificationBundle;
use Alaress\Async\Queue\QueueInterface;

/**
 * "List" which forwards messages intended for mobile clients to a brokered
 * queue.
 *
 * Previous versions of the mobile queue were directly responsible for sending
 * messages to mobile clients: now, it simply adds them to a queue to be
 * processed by another process.
 *
 * This is because this list was unable to handle retries in a stable manner:
 * it's called within a worker, and sending the worker to sleep was causing it
 * to get killed unexpectedly and irrecoverably by its pool; thus causing
 * messages to be lost forever.
 *
 * Note that it is no longer feasible to access messages once they are in the
 * brokered queue: thus `contains`, `drain` and `fetchIntoBundle` are no-ops.
 */
class ForwardToBrokeredQueue implements QueueInterface
{
    /**
     * Constructor
     *
     * @param IBroker $broker     The brokered queue to publish into.
     * @param string  $instanceId The identifier for this Schoolbox instance: used as part of the message's context
     *                            when it is pushed onto the brokered queue.
     * @param int     $userId     The user ID to create a queue for.
     */
    public function __construct(private IBroker $broker, private string $instanceId, private int $userId)
    {
    }

    /**
     * Retrieve the name of this queue.
     *
     * @return string
     */
    public function getName(): string
    {
        return "{$this->userId}:mobile";
    }

    /**
     * It is not currently possible to determine if the mobile queue's message
     * broker contains an instance: thus always return false.
     *
     * @param Instance $instance
     * @return false
     */
    public function contains(Instance $instance): bool
    {
        return false;
    }

    /**
     * It is not currently possible to drain the mobile queue's message broker.
     *
     * @return void
     */
    public function drain(): void
    {
    }

    /**
     * Push the given instance onto the brokered queue.
     *
     * Messages are published with `$this->instanceId` and `$this->userId` as
     * contexts:
     * * instance ID is not yet required, but may be if the brokered queue
     *   becomes instance-agnostic
     * * user ID is required so that `$instance` may be sent to a particular
     *   user
     *
     * @param Instance $instance
     * @return void
     */
    public function push(Instance $instance): void
    {
        $this->broker->publish($instance, [$this->instanceId, $this->userId], $instance->getTTL());
    }

    /**
     * It is not currently possible to access messages in the brokered queue.
     *
     * @param NotificationBundle $bundle
     * @param bool               $dropCache
     * @return void
     */
    public function fetchIntoBundle(NotificationBundle $bundle, bool $dropCache = false): void
    {
    }
}
