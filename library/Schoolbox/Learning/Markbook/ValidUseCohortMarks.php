<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Markbook;

use Schoolbox\Resource\Folder;

/**
 * Class ValidUseCohortMarks
 *
 * Decide whether LMS Cohort Comparison should be activated for $folder
 *
 * There's a global configuration setting which should be used if $folder
 * isn't associated with any year levels
 *
 * @package Schoolbox\Learning\Markbook
 */
class ValidUseCohortMarks
{

    /**
     * Decide whether LMS Cohort Comparison should be activated for $folder
     *
     * @param bool         $configOption
     * @param Folder\Model $folder
     * @return bool
     */
    public function isSatisfiedBy(bool $configOption, Folder\Model $folder): bool
    {

        /* Use the global setting when $folder isn't associated with year level instances
         */
        if (null === $folder->getYearLevels()) {
            return $configOption;
        }

        /* Otherwise, if any of the associated Year Levels want to enable LMS Cohort Comparison,
         * return true
         */
        foreach ($folder->getYearLevels() as $yearLevel) {
            if ($yearLevel->getLmsCohortComparison()) {
                return true;
            }
        }

        /* Otherwise, fall back to the current global setting lms_cohort_comparison
         */
        return $configOption;
    }
}
