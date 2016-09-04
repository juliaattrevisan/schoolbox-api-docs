<?php
/**
 * IsParentAccessAllowed
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
     * IsParentAccessAllowed constructor.
     */
    public function __construct()
    {
    }

    /**
     * Determine if the logged in user should have access to the requested pastoral care record
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

