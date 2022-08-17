<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Pastoral;

use Schoolbox\Filter\AbstractIdFinder;

/**
 * ID finder for the pastoral filter
 */
class IdFinder extends AbstractIdFinder
{
    /**
     * @inheritdoc
     */
    protected function doFiltersGuaranteeEmptySet(array $completeFilters): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    protected function simplifyFilters(array $completeFilters): array
    {
        return $completeFilters;
    }

    /**
     * @inheritdoc
     */
    protected function buildQuery(array $completeFilters, bool $deleted): array
    {
        return [];
    }
}
