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
    protected function doFiltersGuaranteeEmptySet(array $completeFilters)
    {
        // TODO: Implement doFiltersGuaranteeEmptySet() method.
    }

    protected function simplifyFilters(array $completeFilters): array
    {
        // TODO: Implement simplifyFilters() method.
    }

    protected function buildQuery(array $completeFilters, $deleted)
    {
        // TODO: Implement buildQuery() method.
    }
}
