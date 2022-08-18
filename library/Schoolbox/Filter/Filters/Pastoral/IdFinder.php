<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Pastoral;

use Schoolbox\Filter\AbstractIdFinder;
use Schoolbox\User;

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
     * @param User $user
     * @inheritdoc
     */
    protected function buildQuery(array $completeFilters, User $user, bool $deleted): array
    {
        $pb = $this->getMapper()->getPredicateBuilder();
        if (!$deleted) {
            $pb->isNull('p.deleted_at');
        }

        $creatorSelection = $completeFilters[Creator\Definition::class] ?? null;
        if (isset($creatorSelection[Creator\Definition::KEY_CURRENT_USER])) {
            // Add current user (where is that?)
            // May need to pass as a parameter here
        }
        if (isset($creatorSelection[Creator\Definition::KEY_OTHER_USERS])) {
            // Add non-current user (where is that?)
        }

        // Somehow apply class here?
        // That may be done by passing a class code, and applying a student
        // filter?

        $query = "SELECT DISTINCT p.id FROM pastoral p";
        if (count($pb->getWhere()) > 0) {
            $query .= ' WHERE ' . $pb->getWhereIntersect();
        }
        return [$query, $pb->getParams()];
    }
}
