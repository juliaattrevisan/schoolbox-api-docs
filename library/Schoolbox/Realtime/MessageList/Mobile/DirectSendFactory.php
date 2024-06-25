<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\MessageList\Mobile;

use Alaress\Async\Queue\QueueInterface;
use Schoolbox\MobileApp\IServiceManager;

/**
 * Factory for building direct send to mobile queues.
 *
 * @deprecated the direct send will be superseded by a forward of messages to a
 *             brokered queue.
 */
class DirectSendFactory implements IQueueFactory
{
    /**
     * Constructor
     *
     * @param IServiceManager $cloudManager Service which may send messages to mobile devices.
     */
    public function __construct(private IServiceManager $cloudManager)
    {
    }

    /**
     * @inheritdoc
     */
    public function build(int $userId): QueueInterface
    {
        return new DirectSend($this->cloudManager, $userId);
    }
}
