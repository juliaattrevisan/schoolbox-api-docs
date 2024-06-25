<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\MessageList\Mobile;

use Alaress\Async\Queue\QueueInterface;
use Predis\ClientInterface;
use Schoolbox\Realtime\MessageList\Mobile;

/**
 * Simple mobile queue factory: this one just creates a queue backed by Redis
 * only.
 */
class SimpleQueueFactory implements IQueueFactory
{
    /**
     * Constructor
     *
     * @param ClientInterface $redis
     * @param string          $instanceId
     */
    public function __construct(private ClientInterface $redis, private string $instanceId)
    {
    }

    /**
     * @inheritdoc
     */
    public function build(int $userId): QueueInterface
    {
        return new Mobile($this->redis, $this->instanceId, $userId);
    }
}
