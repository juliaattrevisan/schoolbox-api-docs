<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry;

use Sentry\Event;

/**
 * Filter for the Sentry client, used for preventing particular classes of
 * exception from being reported to Sentry.
 */
class ExceptionFilter
{
    /**
     * Do not report to Sentry for exceptions that are of these classes or their
     * subclasses.
     *
     * @var array|string[]
     */
    private $excludedClasses;


    /**
     * Constructor
     *
     * @param string ...$excludedClasses
     */
    public function __construct(string ...$excludedClasses)
    {
        $this->excludedClasses = $excludedClasses;
    }


    /**
     * If the exception in $data matches a an excluded exception, don't send to
     * Sentry.
     *
     * @param Event $event
     * @return Event|null
     */
    public function __invoke(Event $event): ?Event
    {
        foreach ($event->getExceptions() as $exception) {
            foreach ($this->excludedClasses as $excludedClass) {
                if (is_a($exception->getType(), $excludedClass, true)) {
                    return null;
                }
            }
        }

        return $event;
    }
}
