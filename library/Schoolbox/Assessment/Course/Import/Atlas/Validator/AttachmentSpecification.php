<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Validator;

use Alaress\Specification\Base as BaseSpecification;
use Schoolbox\Assessment\Course\Import\Atlas\Api\Attachment;

/**
 * Attachment validation specification
 */
class AttachmentSpecification extends BaseSpecification
{
    /**
     * In order to satisfy the spec, `$candidate` must:
     * * be an `Attachment` instance
     * * have a title
     *
     * @param Attachment $candidate
     * @return bool
     */
    public function isSatisfiedBy($candidate): bool
    {
        if (!$candidate instanceof Attachment) {
            return false;
        }
        if ('' === trim($candidate->getTitle())) {
            return false;
        }
        return true;
    }
}
