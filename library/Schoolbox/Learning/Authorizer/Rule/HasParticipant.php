<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\User;

class HasParticipant extends AbstractAssessableRule
{
    public function authorize(array $params = [], ?User $user = null)
    {
        $assessable = $this->findAssessable($params);

        /* Check to see if there are any participants in the assessment
         */
        if ($assessable->getParticipantUsers()->count() === 0) {
            throw new Unauthorized(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }
    }
}
