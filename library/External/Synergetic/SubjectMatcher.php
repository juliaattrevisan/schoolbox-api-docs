<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace External\Synergetic;

use Schoolbox\Resource\Folder\Code\SubjectMatcherBase;
use function trim;

class SubjectMatcher extends SubjectMatcherBase
{

    /**
     * @inheritDoc
     */
    public function isMatch(string $sbFolderCode, ?string $sisCampus, string $sisClass): bool
    {
        $sisCampus = trim($sisCampus ?? '');
        if ($this->isExactMatch($sbFolderCode, $sisCampus, $sisClass)) {
            // If an exact match, then return true
            return true;
        }
        if (!$this->isFuzzyMatch($sbFolderCode, $sisCampus, $sisClass)) {
            // Not an exact or fuzzy match, return false
            return false;
        }
        // We are a fuzzy match, eg 'ABC' ~= ['X','ABC'].
        // Due to validation rules, we should not have a folder code that is an exact match, eg 'X/ABC'.
        // However, there may be a better match in Synergetic, eg 'ABC' === ['','ABC'].
        // For now, we will let this case be unhandled, and return a potential false positive.
        // TODO Either: a) check Synergetic for a better match, a return !$betterMatchFound, or
        //              b) implement config flag in SB to control whether to expect campus code prefixed to folder code
        return true;
    }

    /**
     * Returns whether the SB folder code is a match for the canonical folder code expected for the campus+class codes
     *
     * @param string $sbFolderCode
     * @param string $sisCampus
     * @param string $sisClass
     * @return bool
     */
    private function isExactMatch(string $sbFolderCode, string $sisCampus, string $sisClass): bool
    {
        return $sbFolderCode === $this->generateFolderCode($sisCampus, $sisClass);
    }

    /**
     * Returns whether the folder code matches the campus+class codes using the fuzzy/fallback mechanism
     *
     * @param string $sbFolderCode
     * @param string $sisCampus
     * @param string $sisClass
     * @return bool
     */
    private function isFuzzyMatch(string $sbFolderCode, string $sisCampus, string $sisClass): bool
    {
        // Fuzzy match if campus code present and matches on class code only
        return $sisCampus !== '' && $sbFolderCode === $sisClass;
    }
}
