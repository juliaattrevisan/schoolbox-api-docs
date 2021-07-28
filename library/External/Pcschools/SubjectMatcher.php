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

        // Just join the campus and class together, without trimming
        if (
            isset($sisCampus)
            && strcasecmp(trim($sisCampus . '/' . $sisClass), trim($sbFolderCode)) === 0
        ) {
            return true;
        }


        return false;
    }
}
