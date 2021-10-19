<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Sync\Bridge\Newic;

class CourseSection extends \External\Canvas\Sync\Bridge\CourseSection
{
    /**
     * TODO: This is on assumption that the last 4 digits of sis_source_id is the year for this course section
     * @inheritDoc
     */
    public function parseYearFromSisSourceId(string $sisSourceId, string $termName): ?string
    {
        if (preg_match('/(\d{4}$)/', $sisSourceId, $matches)) {
            return end($matches);
        }
        return null;
    }

    /**
     * TODO: This is on assumption that course section code matches "sis_source_id of course" - year (likely to be 4 digits... but may be 2 digits)
     * @inheritDoc
     */
    public function parseSubjectCodeFromSisSourceId(string $sisSourceId, string $year): ?string
    {
        if (empty($year)) {
            return $sisSourceId;
        }
        return str_replace($year, '', $sisSourceId);
    }
}
