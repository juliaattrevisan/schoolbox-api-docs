<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace External\Synergetic;

use Schoolbox\Resource\Folder\Code\SubjectMatcherBase;
use function in_array;

class SubjectMatcher extends SubjectMatcherBase
{

    /**
     * @inheritDoc
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
