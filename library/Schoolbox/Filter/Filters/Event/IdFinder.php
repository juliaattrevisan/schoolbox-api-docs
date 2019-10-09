<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Event;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\IMapper;

/**
 * ID finder implementation for event filters.
 *
 * @method static IdFinder getInstance(Filter\Mapper $filterMapper, IMapper $mapper)
 */
class IdFinder extends Filter\AbstractIdFinder
{
    /**
     * I don't yet know whether event filters can ever guarantee an empty set:
     * return false for now.
     *
     * @inheritDoc
     */
    protected function doFiltersGuaranteeEmptySet(array $completeFilters): bool
    {
        return false;
    }


    /**
     * I don't yet know whether event filters can be simplified: return what was
     * passed in for now.
     *
     * @inheritDoc
     */
    protected function simplifyFilters(array $completeFilters): array
    {
        return $completeFilters;
    }


    /**
     * @inheritDoc
     */
    protected function buildQuery(array $completeFilters, bool $deleted): array
    {
        $subQueries     = [];
        $subQueryParams = [];

        // Only the event type filter is currently available
        $eventTypeIds = $completeFilters[EventType\Definition::class] ?? null;
        [$eventTypeWhere, $eventTypeParams] = $this->getClauseForEventTypes($eventTypeIds);

        // Build subqueries for that type
        $subQueries[]   = "SELECT id FROM event WHERE {$eventTypeWhere}";
        $subQueryParams = array_merge($subQueryParams, $eventTypeParams);

        return [implode("\n\n\tUNION\n\n", $subQueries), $subQueryParams];
    }


    /**
     * Build where part and parameters for event type searches.
     *
     * @param Collection|null $eventTypeIds
     * @return array
     */
    private function getClauseForEventTypes(?Collection $eventTypeIds): array
    {
        // No event types selected? select all
        if ($eventTypeIds === null || count($eventTypeIds) === 0) {
            $where  = 'TRUE';
            $params = [];
        } else {
            $ids     = $eventTypeIds->getAll();
            $builder = $this->getMapper()->getPredicateBuilder();
            $builder->in('type', $ids);
            $where  = $builder->getWhere()[0];
            $params = $builder->getParams();
        }
        return [$where, $params];
    }
}
