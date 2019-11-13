<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace External\Synergetic;

use Schoolbox\Resource\Folder\Code\ISubjectMatcher;
use function in_array;

class SubjectMatcher implements ISubjectMatcher
{

    /**
     * Returns whether a Schoolbox folder code is an exact match for a subject's campus+class codes
     *
     * eg
     *  (Synergetic) 'X/ABC' exactly matches ['X','ABC'] or ['','X/ABC']
     *  (TASS) TODO
     *
     * @param string      $sbFolderCode A folder's folder code
     * @param string|null $sisCampus    A subject's campus code
     * @param string      $sisClass     A subject's class code
     * @return bool
     */
    public function isMatch(string $sbFolderCode, ?string $sisCampus, string $sisClass): bool
    {
        $sisCampus = trim($sisCampus ?? '');
        if ($sisCampus !== '') {
            // Subject has campus code, optional whether campus code included in folder code
            return in_array($sbFolderCode, [$sisClass, "$sisCampus/$sisClass"], true);
        }
        // No campus code, folder code must match class code
        return $sbFolderCode === $sisClass;
    }
}
