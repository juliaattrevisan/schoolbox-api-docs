<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Queue\Revoker;

use Alaress\Async\Notification\Instance\Instance;

/**
 * An interface for revoking messages.
 */
interface RevokerInterface
{
    /**
     * Surgically revoke this message from a queue, if it's there.
     *
     * @param Instance $instance
     * @return bool
     */
    public function revoke(Instance $instance): bool;

    /**
     * Surgically revoke this message and any with the same base hash from a
     * queue.
     *
     * @param Instance $instance
     * @return int The number of msgs removed
     */
    public function revokeAllLike(Instance $instance): int;
}
