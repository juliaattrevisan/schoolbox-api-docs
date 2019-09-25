<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace External\Canvas\Sync;

use function preg_match;
use function preg_replace;

/**
 * Responsible for consistently parsing fragments from a Canvas `course_section`.`sis_source_id`.
 *
 * It is expected this field is the concatenation of several SIS fields, eg CONCAT($year, 'C', $subjectCode, 'NOT')
 */
class CourseSectionSisCodeParser
{

    /**
     * Attempt to parse a year from a sisSourceId, eg:
     *
     * 'Y2014C09DRA'    => '2014'
     * 'xxxY2014C09DRA' => '2014'
     * 'xxx'            => null
     *
     * @param string $sisSourceId
     * @return string|null
     */
    public function parseYearFromSisSourceId(string $sisSourceId): ?string
    {
        if (preg_match("/^Y(\d{4})C.+$/", $sisSourceId, $canonicalYearMatch)) {
            // This is the canonical form.
            return $canonicalYearMatch[1];
        }
        if (preg_match("/(\d{4})/", $sisSourceId, $fuzzyYearMatch)) {
            // Fall back to any four digits
            return $fuzzyYearMatch[1];
        }
        return null;
    }


    /**
     * Attempt to parse a Subject Code from a sisSourceId, eg:
     *
     * 'Y2014C09DRANOT' => '09DRA'
     * 'Y2014C09DRA'    => '09DRA'
     * 'C09DRA'         => null
     *
     * @param string $sisSourceId
     * @return string|null
     */
    public function parseSubjectCodeFromSisSourceId(string $sisSourceId): ?string
    {
        // Parse subject code (exact match only, no fuzzy fall back for now)
        if (preg_match("/^Y\d{4}C(.+)$/", $sisSourceId, $canonicalSubjectMatch)) {
            // This is the canonical form.
            return preg_replace("/NOT$/", '', $canonicalSubjectMatch[1]);
        }
        return null;
    }
}
