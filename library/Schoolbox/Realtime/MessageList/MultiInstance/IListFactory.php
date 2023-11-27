<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Realtime\MessageList\MultiInstance;

use Alaress\Async\Queue\QueueInterface;

/**
 * Interface for multi-instance aware list factories.
 *
 * `\Schoolbox\Realtime\MessageList\IListFactory` is the single-instance
 * version of this interface.
 */
interface IListFactory
{
    /**
     * Fetch a list of message instances which are in the `listed` state.
     *
     * @param string $instanceId Fetch instances for
     * @param int    $userId
     * @return QueueInterface
     */
    public function listed(string $instanceId, int $userId): QueueInterface;
}
