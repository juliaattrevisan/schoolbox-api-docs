<?php

/**
 * @author      Dorothea Baker <dorothea.baker@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Permission\Access\Access;
use Schoolbox\User;
use InvalidArgumentException;

class CanAcknowledgeAssessment extends AbstractParticipationRule
{

    /**
     * @var Access
     */
    private $access;


    /**
     * @param Access $access
     */
    public function __construct(Access $access)
    {
        $this->access = $access;
    }


    /**
     * @param array     $params
     * @param User|null $user
     * @return bool
     * @throws Failure\Base If unable to respond because student has reached response limit
     * @throws InvalidArgumentException If unable to locate an IAssessable or User instance in route params
     */
    public function authorize(array $params = [], User $user = null)
    {
        $assessment = $this->findAssessable($params);

        if (!$assessment instanceof DueWork\Model) {
            throw new Failure\InvalidPath();
        }

        $folder     = $assessment->getBaseContext()->getFolder();
        $student    = $this->findStudent($params);

        if (!$user) {
            throw new InvalidArgumentException('No user supplied to authorize');
        }

        if ($this->access->isInstructor($user, $folder) && $assessment->canInstructorCreateResponse($user, $student)) {
            return true;
        }

        if ($this->access->isLearner($user, $folder) && $assessment->canLearnerCreateAcknowledgement($student)) {
            return true;
        }

        throw new Failure\Unauthorized();
    }
}
