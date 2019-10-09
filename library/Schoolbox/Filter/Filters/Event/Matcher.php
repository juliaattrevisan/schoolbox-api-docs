<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Event;

use Calendar\Event\Type\EventInterface;
use Schoolbox\Collection;

/**
 * A post-fetch check for whether the event matches the filter.
 *
 * May be passed directly to `array_filter` or `\Schoolbox\Collection::filter`
 * (or `\Calendar\Event\Collection::filter`: that class needs to cease existing)
 *
 * Pre-fetch filtering of events using an ID finder won't work: this is because
 * events may come from sources other than the Schoolbox database.
 */
class Matcher
{
    /**
     * @var array|Collection[]
     */
    private $filters;


    /**
     * Constructor
     *
     * @param array $filters
     */
    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }


    /**
     * Does this calendar event match the list of provided filters?
     *
     * @param EventInterface $event
     * @return bool
     */
    public function __invoke(EventInterface $event): bool
    {
        // The only thing I know how to check yet is the event type.
        foreach ($this->filters as $filterClass => $filter) {
            if ($filterClass === EventType\Definition::class) {
                // No items selected: filter in
                if ($filter === null || count($filter) === 0) {
                    return true;
                }

                // Check this event's type, and compare it to what is selected
                $type = (int)$event->getType();
                if ($type <= 0) {
                    // No type? This filter doesn't apply to this event
                    return true;
                }

                return $filter->contains($type);
            }
        }
        return true;
    }
}
