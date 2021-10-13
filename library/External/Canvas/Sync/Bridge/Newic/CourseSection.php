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
