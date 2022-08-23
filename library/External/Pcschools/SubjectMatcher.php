<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Pcschools;

use Schoolbox\Resource\Folder\Code\SubjectMatcherBase;

use function trim;

/**
 * PCSchool subject matcher.
 *
 * Allows for leading/trailing spaces on the SIS campus and class values.
 */
class SubjectMatcher extends SubjectMatcherBase
{
    /**
     * @inheritDoc
     */
    public function isMatch(string $sbFolderCode, ?string $sisCampus, string $sisClass): bool
    {
        // Standard match
        if (parent::isMatch($sbFolderCode, $sisCampus, $sisClass)) {
            return true;
        }

        // The PCSchool part of the class code often contains a space.
        // (e.g. "21.00/ 9ART1E1"
        // Account for that, if a SIS campus was passed in.
        if (isset($sisCampus)) {
            $sbFolderCodeParts = explode('/', $sbFolderCode, 2);
            if (count($sbFolderCodeParts) === 1) {
                $sbFolderCampus  = null;
                [$sbFolderClass] = $sbFolderCodeParts;
            } else {
                [$sbFolderCampus, $sbFolderClass] = $sbFolderCodeParts;
            }

            // Does the folder code contain a campus?
            // If so compare the SIS and SB campus and class codes directly.
            if (isset($sbFolderCampus)) {
                return strcasecmp(trim($sbFolderCampus), trim($sisCampus)) === 0
                    && strcasecmp(trim($sbFolderClass), trim($sisClass)) === 0;
            }
        }

        return false;
    }

    /**
     * Pcschools sometimes require a space in sisClass
     * @inheritDoc
     */
    public static function generateFolderCode(?string $sisCampus, string $sisClass): string
    {
        // This is the canonical implementation (even for Synergetic which has an alternative fuzzy match fallback)
        $sisCampus = trim($sisCampus ?? '');
        return ($sisCampus === '' ? '' : "$sisCampus/") . $sisClass;
    }
}
