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

        /* Potentially refine this to throw \InvalidArgumentException for invalid $canvasCourseCodes
         * and/or \OutOfRangeException for $canvasCourseCodes outside the current year
         */
        return $matches[1] ?? null;
    }
}
