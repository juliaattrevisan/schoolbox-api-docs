<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\User;
use Schoolbox\Error;

class IsAssessmentOpen extends AbstractParticipationRule
{

    /**
     * @inheritdoc
     * @throws \InvalidArgumentException If unable to locate an Assessment instance in $params
     * @throws Unauthorized If Assessment is not open
     */
    public function authorize(array $params = [], User $user = null)
    {
        $assessment = $this->findAssessable($params);
        $student = $this->findStudent($params);
        
        if (!$assessment->isOpen($student)) {
            throw new Unauthorized(Error\Mapper::ERROR_ASSESSMENT_CLOSED);
        }
    }
}
