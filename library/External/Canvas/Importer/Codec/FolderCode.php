<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Importer\Codec;

/**
 * Mine out the folder code from a canvas course code
 *
 * Canvas course codes are in this format:
 * 11ENGADV2020
 *
 * The equivalent schoolbox folder code is bereft of the year sufffix:
 * 11ENGADV
 *
 * The only courses whose announcements we need to we need to worry
 * about importing are in the format:
 * 11ENGADV2020
 *
 * All other courses that we put into our local cache db can be disregarded
 * (they are just random groups and stuff)
 */
class FolderCode
{

    /**
     * Return the folder code that should be associated with $canvasCourseCode
     * If $canvasCourseCode is invalid, returns null
     *
     * Only match canvas course codes that ended with $currentYear as a suffix
     *
     * @param int    $currentYear
     * @param string $canvasCourseCode
     * @return string|null
     */
    public function decode(int $currentYear, string $canvasCourseCode): ?string
    {
        /* Potentially matches current year and future years
         * e.g. 09CIVPEDAHA2021 09CIVPEDAHA2022 09CIVPEDAHA2023
         *
         * If not, you could just select "WHERE LIKE %$currentYear"
         *
         * But I have a feeling that this won't be the only subset of courses to import...
         */
        preg_match("/(.*){$currentYear}$/", $canvasCourseCode, $matches);

        /* For IB International Baccalaureate, some of their subjects run over multiple years
         * The canvas course_dim.code value could have a suffix in the future, but that should
         * display in the current year
         *
         * e.g. 11SCIPIBHLA2023 is a course_dim.code which might run over multiple years and
         * should be found for 2022 as well as when the current year becomes 2023
         *
         * Also helps support a rollover phasing when schools start next year's stuff in
         * this year, and their codes won't match the current year.
         */
        if (!isset($matches[1])) {
            /* Try next year
             */
            $nextYear = $currentYear+1;

            preg_match("/(.*){$nextYear}$/", $canvasCourseCode, $matches);
        }

        /* Potentially refine this to throw \InvalidArgumentException for invalid $canvasCourseCodes
         * and/or \OutOfRangeException for $canvasCourseCodes outside the current year
         */
        return $matches[1] ?? null;
    }
}
