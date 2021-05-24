<?php

/**
 * Determine if parent access is allowed in Schoolbox instances config
 *
 * @author      Damian Sloane <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Config;
use Schoolbox\User;

class IsParentAccessAllowed implements IAuthorizationRule
{
    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = [], User $user = null)
    {
        if (!Config::get('pastoral_parent_access')) {
            throw new Unauthorized();
        }

        return true;
    }
}
