<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry;

use Sentry\Event;

/**
 * Composite Sentry event filter: this will pass the event through a chain of
 * sub-filters.
 * * the event returned by each filter is passed to the next in the chain
 * * if any filter in the chain returns `null`, this filter returns `null`
 */
class CompositeFilter implements IFilter
{
    /**
     * @var array<callable(Event): ?Event>
     */
    private array $filters;

    /**
     * Constructor
     *
     * @param callable(Event): ?Event ...$filters
     */
    public function __construct(callable ...$filters)
    {
        $this->filters = $filters;
    }

    /**
     * @inheritdoc
     */
    public function __invoke(Event $event): ?Event
    {
        foreach ($this->filters as $filter) {
            $event = $filter($event);
            if (!$event) {
                return null;
            }
        }
        return $event;
    }
}
