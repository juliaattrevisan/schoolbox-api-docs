<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\MessageList\Mobile;

use Alaress\Async\Queue\QueueInterface;

/**
 * Interface for factories which may build a queue for sending to mobiles.
 */
interface IQueueFactory
{
    /**
     * Build a queue for sending messages to mobile devices.
     *
     * @param int $userId
     * @return QueueInterface
     */
    public function build(int $userId): QueueInterface;
}
