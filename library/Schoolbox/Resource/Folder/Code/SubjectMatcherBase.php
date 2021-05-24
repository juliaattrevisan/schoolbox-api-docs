<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Folder\Code;

use function trim;

class SubjectMatcherBase implements ISubjectMatcher
{

    /** @inheritDoc */
    public function isMatch(string $sbFolderCode, ?string $sisCampus, string $sisClass): bool
    {
        return strcasecmp($sbFolderCode, $sisClass) === 0 || strcasecmp($sbFolderCode, $this->generateFolderCode($sisCampus, $sisClass)) === 0;
    }

    /**
     * Consistently generate a Schoolbox folder code from a SIS subject's campus+class codes
     *
     * TODO, consider a synergetic-specific implementation of this that uses a config flag to determine
     *       whether the campusCode is prefixed.
     *
     * @param string|null $sisCampus
     * @param string      $sisClass
     * @return string
     */
    public static function generateFolderCode(?string $sisCampus, string $sisClass): string
    {
        // This is the canonical implementation (even for Synergetic which has an alternative fuzzy match fallback)
        $sisCampus = trim($sisCampus ?? '');
        return ($sisCampus === '' ? '' : "$sisCampus/") . trim($sisClass);
    }
}
