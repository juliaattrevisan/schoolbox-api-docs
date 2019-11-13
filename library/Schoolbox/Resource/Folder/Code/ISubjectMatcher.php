<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Folder\Code;

/**
 * Responsible for determining is a Schoolbox Folder matches a SIS Subject.
 *
 * It performs this by comparing the Folder's folderCode to the Subject's campus and class codes.
 *
 * The implementation of this interface is SIS-specific.
 */
interface ISubjectMatcher
{
    /**
     * Returns whether a Schoolbox folder code is an exact match for a SIS subject's campus+class codes
     *
     * @param string      $sbFolderCode A SB folder's code
     * @param string|null $sisCampus    A SIS subject's campus code
     * @param string      $sisClass     A SIS subject's class code
     * @return bool
     */
    public function isMatch(string $sbFolderCode, ?string $sisCampus, string $sisClass): bool;
}
