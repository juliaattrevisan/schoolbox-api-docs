<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid;

/**
 * Spec pattern for validating rubric grids.
 */
class ValidRubricGrid extends \Alaress\Specification\Base
{
    /**
     * `$candidate` satisfies this pattern if it contains at least one
     * indicator usage. (This implies that the grid has at least one
     * capability usage, too.)
     *
     * (This doesn't validate if the capability or indicator usages are
     *  themselves valid.)
     *
     * @param Grid $candidate
     * @return bool
     */
    public function isSatisfiedBy($candidate): bool
    {
        if (!$candidate instanceof Grid) {
            return false;
        }

        foreach ($candidate->getCapabilityUsages() as $capabilityUsage) {
            if (\count($capabilityUsage->getIndicatorUsages())) {
                return true;
            }
        }

        return false;
    }
}
