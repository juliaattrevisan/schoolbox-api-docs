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
     * @inheritdoc
     */
    protected function buildQuery(array $completeFilters, User $user, bool $deleted): array
    {
        $joins = [];
        $pb    = $this->getMapper()->getPredicateBuilder();
        if (!$deleted) {
            $pb->isNull('p.deleted_at');
        }

        // Creator selection
        $creatorSelectionPb = $this->getMapper()->getPredicateBuilder();
        $creatorSelection   = $completeFilters[Creator\Definition::class] ?? null;
        $filterByCreator    = false;
        if (isset($creatorSelection[Creator\Definition::KEY_CURRENT_USER])) {
            // Add current user
            $creatorSelectionPb = $creatorSelectionPb->equals('p.owner_id', $user->getId());
            $filterByCreator    = true;
        }
        if (isset($creatorSelection[Creator\Definition::KEY_OTHER_USERS])) {
            // Add non-current user
            $creatorSelectionPb = $creatorSelectionPb->notEquals('p.owner_id', $user->getId());
            $filterByCreator    = true;
        }
        if ($filterByCreator) {
            $pb = $pb->any($creatorSelectionPb);
        }

        // Type/subtype selection
        $typeSelection = $completeFilters[Type\Definition::class] ?? null;
        if (isset($typeSelection)) {
            $allTypesSelectionPb = null;
            foreach ($typeSelection as $typeKey) {
                $allTypesSelectionPb ??= $this->getMapper()->getPredicateBuilder();

                // Construct predicate builder for type/subtype:
                // If there's a subtype: both it and the type must match
                $typeSelectionPb = $this->getMapper()->getPredicateBuilder();
                $keyParts        = Type\KeyConverter::extractTypeAndSubtype($typeKey);
                $typeSelectionPb = $typeSelectionPb->equals('p.type_id', $keyParts['type']);
                if (isset($keyParts['subtype'])) {
                    $typeSelectionPb = $typeSelectionPb->equals('p.subtype_id', $keyParts['subtype']);
                }
                $allTypesSelectionPb = $allTypesSelectionPb->all($typeSelectionPb);
            }
            if ($allTypesSelectionPb) {
                $pb = $pb->any($allTypesSelectionPb);
            }
        }

        // Severity selection
        $severitySelection = $completeFilters[Severity\Definition::class] ?? null;
        if (isset($severitySelection)) {
            $pb = $pb->in('p.severity_id', $severitySelection);
        }

        // Action required selection
        $actionSelectionPb = $this->getMapper()->getPredicateBuilder();
        $actionSelection   = $completeFilters[Action\Definition::class] ?? null;
        $filterByAction    = false;
        if (isset($actionSelection[Action\Definition::KEY_ACTION_NOT_REQUIRED])) {
            $filterByAction    = true;
            $actionSelectionPb = $actionSelectionPb
                ->isNull('pra.completed')
                ->isTrue('pra.completed');
        }
        if (isset($actionSelection[Action\Definition::KEY_ACTION_REQUIRED])) {
            $filterByAction    = true;
            $actionSelectionPb = $actionSelectionPb
                ->isFalse('pra.completed');
        }
        if ($filterByAction) {
            $joins[] = 'LEFT JOIN pastoral_record_action pra ON p.id = pra.pastoral_id';
            $pb      = $pb->any($actionSelectionPb);
        }

        // Student selection (hardcoded filter)
        $studentSelection = $completeFilters[Student\Definition::class] ?? null;
        if (isset($studentSelection)) {
            $pb = $pb->in('p.student_id', $studentSelection);
        }

        $query = "SELECT DISTINCT p.id FROM pastoral p";
        if (count($joins)) {
            $query .= ' ' . implode(' ', $joins);
        }
        if (count($pb->getWhere()) > 0) {
            $query .= ' WHERE ' . $pb->getWhereIntersect();
        }
        return [$query, $pb->getParams()];
    }
}
