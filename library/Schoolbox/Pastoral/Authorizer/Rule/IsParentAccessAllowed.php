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
use Schoolbox\Config\ConfigMapper;
use Schoolbox\User;

class IsParentAccessAllowed implements IAuthorizationRule
{
    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = [], User $user = null)
    {
        if (!Config::get(ConfigMapper::NAME_PASTORAL_PARENT_ACCESS_ENABLED)) {
            throw new Unauthorized();
        }

        return true;
    }
}
