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
        // Default case is that if a campus is present, it must be included in folder code
        $exactFolderCode = ($sisCampus ?? '') === '' ? $sisClass : "$sisCampus/$sisClass";
        return $sbFolderCode === $exactFolderCode;
    }

    /** @inheritDoc */
    public function generateFolderCode(?string $sisCampus, string $sisClass): string
    {
        // This is the canonical implementation (even for Synergetic which has an alternative fuzzy match fallback)
        $sisCampus = trim($sisCampus ?? '');
        return ($sisCampus === '' ? '' : "$sisCampus/") . trim($sisClass);
    }
}
