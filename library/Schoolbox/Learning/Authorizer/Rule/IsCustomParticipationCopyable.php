<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\User;

class IsCustomParticipationCopyable extends AbstractAssessableRule
{
    public function authorize(array $params = [], ?User $user = null)
    {
        $assessable = $this->findAssessable($params);

        if (!$assessable->isCustomParticipationCopyable()) {
            throw new Unauthorized(\sprintf(
                '"%s" (%s) cannot be copied via custom participation',
                $assessable->getTitle(),
                $assessable->getDefaultTypeNameTranslated(),
            ));
        }
    }
}
