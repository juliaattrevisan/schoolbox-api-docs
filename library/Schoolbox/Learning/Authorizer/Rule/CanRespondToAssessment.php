<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\Permission\Access\Access;
use Schoolbox\User;
use InvalidArgumentException;

class CanRespondToAssessment extends AbstractParticipationRule
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
     * @throws Unauthorized If unable to respond because student has reached response limit
     * @throws InvalidArgumentException If unable to locate an IAssessable or User instance in route params
     */
    public function authorize(array $params = [], User $user = null)
    {
        $assessment = $this->findAssessable($params);
        $folder     = $assessment->getBaseContext()->getFolder();
        $student    = $this->findStudent($params);

        if (!$user) {
            throw new InvalidArgumentException('No user supplied to authorize');
        }

        if ($this->access->isInstructor($user, $folder) && $assessment->canInstructorCreateResponse($user, $student)) {
            return true;
        }

        $a = $this->access->isLearner($user, $folder);
        $b = $assessment->canLearnerCreateResponse($student);

        if ($this->access->isLearner($user, $folder) && $assessment->canLearnerCreateResponse($student)) {
            return true;
        }

        throw new Unauthorized();
    }
}
