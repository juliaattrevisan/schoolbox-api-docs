<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\InvalidPath;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\LessonPlan\Model as LessonPlan;
use Schoolbox\User;

class IsCustomParticipationEnabled extends AbstractAssessableRule
{
    public function authorize(array $params = [], ?User $user = null)
    {
        $assessable = $this->findAssessable($params);

        /* If the assessment is a customised assessment then shouldn't allow customisation from it
         */
        if (null !== $assessable->getOriginalAssessmentId()) {
            throw new InvalidPath();
        }

        /* Only allow the assessment that can have custom participation have access
         */
        if (
            $assessable instanceof Project ||
            $assessable instanceof LessonPlan
        ) {
            throw new InvalidPath();
        }
    }
}
