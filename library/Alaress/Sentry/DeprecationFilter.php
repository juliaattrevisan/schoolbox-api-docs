<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry;

use Schoolbox\Deprecation\Registry;
use Sentry\Event;

/**
 * Filter for errors raised as part of a deprecation warning.
 *
 * This uses the rules set out in the deprecation registry: if the deprecation
 * was raised in vendor code, the event is not reported.
 */
class DeprecationFilter implements IFilter
{
    /**
     * Constructor
     *
     * @param Registry $registry
     */
    public function __construct(private Registry $registry)
    {
    }

    public function __invoke(Event $event): ?Event
    {
        // Figure out the details of this exception
        return $event;
    }
}
