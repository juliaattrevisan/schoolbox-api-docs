<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\User;

class IsCustomParticipationEnabled extends AbstractAssessableRule
{
    public function authorize(array $params = [], User $user = null)
    {
        $assessable = $this->findAssessable($params);

        /* If the assessment is a customised assessment then shouldn't allow customisation from it
         */
        if (null !== $assessable->getOriginalAssessmentId()) {
            throw new Unauthorized(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }

        /* Only allow the assessment that can have custom participation have access
         */
        $assessmentType = AssessmentType::objectToString($assessable);
        if (
            AssessmentType::TYPE_PROJECT === $assessmentType ||
            AssessmentType::TYPE_LESSON_PLAN === $assessmentType
        ) {
            throw new Unauthorized(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }
    }
}
