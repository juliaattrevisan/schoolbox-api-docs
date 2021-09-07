<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\User;
use InvalidArgumentException;

class IsSafeExamBrowserRequired extends AbstractAssessableRule
{

    /**
     * Check assessment requires SEB in order to be visible
     *
     * @inheritdoc
     * @throws InvalidArgumentException If unable to locate required params in route args
     */
    public function authorize(array $params = [], User $user = null)
    {
        if (!$this->findAssessable($params)->getSebEnabled()) {
            throw new Unauthorized('Assessment does not require SEB');
        }
    }
}
