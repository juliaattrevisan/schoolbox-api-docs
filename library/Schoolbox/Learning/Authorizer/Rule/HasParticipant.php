<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\InvalidPath;
use Schoolbox\User;

class HasParticipant extends AbstractAssessableRule
{
    public function authorize(array $params = [], ?User $user = null)
    {
        $assessable = $this->findAssessable($params);

        /* Check to see if there are any participants in the assessment
         */
        if ($assessable->getParticipantUsers()->count() === 0) {
            /* TODO Technical Uplift Candidate
             *  This doesn't care about excluded learners which might lead to misunderstandings about how many actual
             *  participants exist in an activity later. Think about whether we need to iterate over getParticipants()
             *  and throw InvalidPath only when everyone's been excluded (ie. return when there's at least one
             *  non-excluded person)
             */
            throw new InvalidPath();
        }
    }
}
