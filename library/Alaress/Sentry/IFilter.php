<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry;

use Sentry\Event;

/**
 * Common interface for Sentry event filters.
 *
 * An implementation of this interface should be usable as the `before_send`
 * hook in a Sentry configuration:
 * @see https://docs.sentry.io/platforms/php/configuration/options/#before-send
 */
interface IFilter
{
    /**
     * Adjust or skip reporting of this event before sending.
     *
     * @param Event $event
     * @return Event|null
     */
    public function __invoke(Event $event): ?Event;
}
