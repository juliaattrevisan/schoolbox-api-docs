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

    protected function simplifyFilters(array $completeFilters): array
    {
        // TODO: Implement simplifyFilters() method.
    }

    protected function buildQuery(array $completeFilters, $deleted): array
    {
        // TODO: Implement buildQuery() method.
    }
}
