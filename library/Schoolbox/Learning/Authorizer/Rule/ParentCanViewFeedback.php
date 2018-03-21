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
 * Is current user a parent of the specified student, and is the assessment configured to
 * permit parents to see feedback?
 */
class ParentCanViewFeedback extends AbstractCanViewFeedback
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

        $assessment = $this->findAssessable($params);
        $student    = $this->findStudent($params)->loadParents();

        // If there's feedback, check it's published
        try {
            $feedback = $this->findFeedback($params);
            if (!$feedback->isPublished()) {
                throw new Unauthorized('Feedback is not published. Parents cannot view draft feedback');
            }
        } catch (\InvalidArgumentException $e) {
            // Let this continue, in case the only thing being checked here is a Kami doc
        }

        if (!$user->isParentOf($student)) {
            throw new Unauthorized('Current user is not a parent of the specified student');
        }

        if (!$assessment->isFeedbackVisibleToUser($user)) {
            throw new Unauthorized('The assessment is configured to not permit parents to see feedback');
        }
    }
}
