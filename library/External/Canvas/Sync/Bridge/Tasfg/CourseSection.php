<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Sync\Bridge\Tasfg;

class CourseSection extends \External\Canvas\Sync\Bridge\CourseSection
{
    /**
     * @inheritDoc
     */
    public function syncCanvasMarks(): bool
    {
        return false;
    }

    /**
     * Attempt to parse a Subject Code from a sisSourceId and year.
     * +----------------+-----------+-------------+
     * | sis_source_id  | term_name | term_sis_id |
     * +----------------+-----------+-------------+
     * | 2025 9SCA 02   | 2025 Term | 2025        |
     * | 2025 9SCA 01   | 2025 Term | 2025        |
     * | NULL           | 2025 Term | 2025        |
     *
     * Above is what has been seen in the school's data
     * we can see that the subject code is everything after the year and space
     * this is what matches in Edumate and was created in schoolbox
     *
     * @param string      $sisSourceId
     * @param string      $year
     * @param string|null $termSisSourceId
     * @return string|null
     */
    public function parseSubjectCodeFromSisSourceId(string $sisSourceId, string $year, ?string $termSisSourceId): ?string
    {
        if (empty($year)) {
            return $sisSourceId;
        }
        return str_replace("$year ", '', $sisSourceId);
    }
}
