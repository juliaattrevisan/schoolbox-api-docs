<?php
/**
 * IsParentAccessAllowed - Determine if parent access is allowed in Schoolbox instances config
 *
 * @author      Damian Sloane <steve.chan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Authorizer\Rule;

use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\User;
use Alaress\Authorizer\Failure\Unauthorized;

class IsParentAccessAllowed implements IAuthorizationRule
{
    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null)
    {
        if (!\Config::get('pastoral_parent_access')) {
            throw new Unauthorized();
        }

        return true;
    }
}

