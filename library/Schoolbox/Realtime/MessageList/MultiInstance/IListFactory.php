<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\MessageList\MultiInstance;

use Alaress\Async\Queue\QueueInterface;

/**
 * Interface for multi-instance aware list factories.
 *
 * `\Schoolbox\Realtime\MessageList\IListFactory` is the single-instance
 * version of this interface.
 *
 * Currently, only `listed` is defined: that is because only listed messages
 * need to be fetched by a multi-instance process (in this case, the socket
 * daemon's worker). It'd be simple to copy the other methods to this class
 * when required (e.g. should the schedule daemon is made multi-instance).
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
