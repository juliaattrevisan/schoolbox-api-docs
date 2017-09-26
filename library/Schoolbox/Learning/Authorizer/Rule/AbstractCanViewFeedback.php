<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\User;
use Schoolbox\Learning\Assessment\DueWork\Response;

abstract class AbstractCanViewFeedback implements IAuthorizationRule
{

    /**
     * Locate an IAssessable instance in supplied params
     *
     * @param array $params
     * @return IAssessable
     * @throws \InvalidArgumentException If unable to locate an IAssessable instance
     */
    protected function findAssessment(array $params)
    {
        if (isset($params['assessment']) && $params['assessment'] instanceof IAssessable) {
            return $params['assessment'];
        }
        if (isset($params['participant']) && $params['participant'] instanceof Participant) {
            /** @var Participant $participant */
            $participant = $params['participant'];
            return $participant->getAssessable();
        }
        throw new \InvalidArgumentException('Unable to locate an assessment in supplied params');
    }

    /**
     * Locate a student User instance in supplied params
     *
     * @param array $params
     * @return User
     * @throws \InvalidArgumentException If unable to locate a student User instance
     */
    protected function findStudent(array $params)
    {
        if (isset($params['student']) && $params['student'] instanceof User) {
            return $params['student'];
        }
        if (isset($params['participant']) && $params['participant'] instanceof Participant) {
            /** @var Participant $participant */
            $participant = $params['participant'];
            return $participant->getUser();
        }
        if (isset($params['response']) && $params['response'] instanceof Response\Model) {
            /** @var Response\Model $response */
            $response = $params['response'];
            return $response->getSubmittedBy();
        }
        throw new \InvalidArgumentException('Unable to locate a student in supplied params');
    }


    /**
     * @param array $params
     * @return IFeedback
     * @throws \InvalidArgumentException If unable to find any feedback instance in params
     */
    protected function findFeedback(array $params)
    {
        if (isset($params['feedback']) && $params['feedback'] instanceof IFeedback) {
            return $params['feedback'];
        }
        throw new \InvalidArgumentException('Unable to locate feedback in supplied params');
    }
}
