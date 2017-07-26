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
use Schoolbox\User;
use Schoolbox\Error;

class IsAssessmentOpen implements IAuthorizationRule
{

    /**
     * @inheritdoc
     * @throws \InvalidArgumentException If unable to locate an Assessment instance in $params
     * @throws Unauthorized If Assessment is not open
     */
    public function authorize(array $params = [], User $user = null)
    {
        $assessment = $this->findAssessment($params);
        if (!$assessment->isOpen()) {
            throw new Unauthorized(Error\Mapper::ERROR_ASSESSMENT_CLOSED);
        }
    }


    /**
     * @param array $params
     * @return IAssessable
     * @throws \InvalidArgumentException If unable to locate an Assessment instance in $params
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
        throw new \InvalidArgumentException('Unable to locate an assessment instance in $params');
    }
}
