<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\User;

/**
 * Is current user the participation student, and is the assessment configured to
 * permit students to see feedback?
 */
class StudentCanViewFeedback extends AbstractCanViewFeedback
{

    /**
     * @inheritdoc
     * @throws \InvalidArgumentException
     * @throws Unauthorized
     */
    public function authorize(array $params = [], User $user = null)
    {
        if(!$user){
            throw new \InvalidArgumentException('User object required.');
        }

        $assessment = $this->findAssessment($params);
        $student    = $this->findStudent($params)->loadParents();

        if ($user->getId() !== $student->getId()) {
            throw new Unauthorized('Current user is not participation student');
        }
        if (!$assessment->isFeedbackVisibleToUser($user)) {
            throw new Unauthorized('This assessment is configured to not permit students to see feedback');
        }
    }
}
