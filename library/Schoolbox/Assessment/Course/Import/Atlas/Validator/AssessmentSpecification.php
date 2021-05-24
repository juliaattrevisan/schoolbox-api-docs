<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Validator;

use Alaress\Specification\Base as BaseSpecification;
use Schoolbox\Assessment\Course\Import\Atlas\Api\Assessment;

/**
 * Assessment validation specification
 */
class AssessmentSpecification extends BaseSpecification
{
    /**
     * In order to satisfy the spec, `$candidate` must:
     * * be an `Assessment` instance
     * * have a title
     *
     * @param Assessment $candidate
     * @return bool
     */
    public function isSatisfiedBy($candidate): bool
    {
        if (!$candidate instanceof Assessment) {
            return false;
        }
        if ('' === trim($candidate->getTitle())) {
            return false;
        }
        return true;
    }
}
