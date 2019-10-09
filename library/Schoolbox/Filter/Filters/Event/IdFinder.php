<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Event;

use Schoolbox\Filter;

/**
 * ID finder implementation for event filters.
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
     * @param array $completeFilters
     * @param bool  $deleted
     * @return array
     */
    protected function buildQuery(array $completeFilters, bool $deleted): array
    {
        // TODO: Implement buildQuery() method.
    }
}
