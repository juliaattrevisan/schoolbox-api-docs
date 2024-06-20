<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\MessageList;

use Alaress\Async\Queue\QueueInterface;

/**
 * Interface for factory for building message "queues" for direct publication
 * (these are not queues at all, but instead they send a message directly).
 *
 * Currently, this builds only the email queue: all other queues are handled
 * by other processes.
 */
interface IPublicationFactory extends IListFactory
{
    /**
     * Build the message queue for sending via email.
     *
     * @param int $userId
     * @return QueueInterface
     */
    public function email($userId);
}
