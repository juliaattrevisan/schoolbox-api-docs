<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Learning\Badge\Award\Model as BadgeAward;
use Schoolbox\User;

class IsAwardGoal implements IAuthorizationRule
{
    /**
     * Ensures the current award is goal
     *
     * @param array     $params
     * @param User|null $user
     * @return bool
     */
    public function authorize(array $params = [], ?User $user = null)
    {
        if (
            isset($params['award'])
            && $params['award'] instanceof BadgeAward
            && $params['award']->isGoal()
        ) {
            return true;
        }
        throw new Unauthorized();
    }
}
