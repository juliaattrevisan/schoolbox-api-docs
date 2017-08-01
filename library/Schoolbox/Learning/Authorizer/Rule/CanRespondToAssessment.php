<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Permission\Access\Access;
use Schoolbox\User;

class CanRespondToAssessment implements IAuthorizationRule
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
     * @throws \InvalidArgumentException If unable to locate an IAssessable or User instance in route params
     */
    public function authorize(array $params = [], User $user = null)
    {
        $assessment = $this->findAssessment($params);
        $folder     = $assessment->getBaseContext()->getFolder();
        $student    = $this->findStudent($params);

        if ($this->access->isInstructor($user, $folder) && $assessment->canInstructorCreateResponse($user, $student)) {
            return true;
        }

        if ($this->access->isLearner($user, $folder) && $assessment->canLearnerCreateResponse($student)) {
            return true;
        }

        throw new Unauthorized();
    }


    /**
     * @param array $params
     * @return IAssessable
     * @throws \InvalidArgumentException If unable to find an IAssessable instance in route params
     */
    private function findAssessment(array $params)
    {
        if (isset($params['assessment']) && $params['assessment'] instanceof IAssessable) {
            return $params['assessment'];
        }
        if (isset($params['participant']) && $params['participant'] instanceof Participant) {
            /** @var Participant $participant */
            $participant = $params['participant'];
            return $participant->getAssessable();
        }
        throw new \InvalidArgumentException('Unable to locate an IAssessable instance in route params');
    }


    /**
     * @param array $params
     * @return User
     * @throws \InvalidArgumentException If unable to find a User instance in route params
     */
    private function findStudent(array $params)
    {
        if (isset($params['student']) && $params['student'] instanceof User) {
            return $params['student'];
        }
        if (isset($params['participant']) && $params['participant'] instanceof Participant) {
            /** @var Participant $participant */
            $participant = $params['participant'];
            return $participant->getUser();
        }
        throw new \InvalidArgumentException('Unable to locate a User instance in route params');
    }
}
