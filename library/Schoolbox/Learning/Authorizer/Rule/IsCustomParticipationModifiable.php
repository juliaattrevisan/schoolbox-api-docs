<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\InvalidPath;
use Schoolbox\User;

class IsCustomParticipationModifiable extends AbstractAssessableRule
{
    public function authorize(array $params = [], ?User $user = null)
    {
        $assessable = $this->findAssessable($params);

        if (!$assessable->isCustomParticipationModifiable()) {
            throw new InvalidPath();
        }
    }
}
