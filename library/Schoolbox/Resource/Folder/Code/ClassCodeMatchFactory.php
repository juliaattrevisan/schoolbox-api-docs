<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Folder\Code;

use External\Pcschools;
use External\Synergetic;

class ClassCodeMatchFactory
{
    /**
     * Returns an implementation suitable for the specified external type
     *
     * @param string $externalType
     * @return ISubjectMatcher
     */
    public function build(string $externalType): ISubjectMatcher
    {
        if ($externalType === 'synergetic') {
            return new Synergetic\SubjectMatcher();
        }
        if ($externalType === 'pcschools') {
            return new Pcschools\SubjectMatcher();
        }
        return new SubjectMatcherBase();
    }
}
