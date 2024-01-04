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
 *
 * If the class name of the exception in the event matches an excluded class,
 * skip sending this event.
 */
class ExceptionFilter implements IFilter
{
    /**
     * Do not report to Sentry for exceptions that are of these classes or their
     * subclasses.
     *
     * @var array|string[]
     */
    private array $excludedClasses;

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
     * @inheritdoc
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
