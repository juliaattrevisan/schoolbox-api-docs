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
     * Attempt to parse a Subject Code from a sisSourceId
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
