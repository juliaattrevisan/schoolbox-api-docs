<?php
/**
 * IsPastoralModerator
 *
 * @author      Damian Sloane <steve.chan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Authorizer\Rule;

use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Alaress\Authorizer\Failure\Unauthorized;

class IsPastoralModerator implements IAuthorizationRule
{
    /**
     * Determine if the logged in user should have access to the requested pastoral care record
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null)
    {
        if ($user->getAccessList()->filterBy('service', UserAccess::SERVICE_PASTORAL)->count() > 0) {
            return true;
        }

        // BZZZZZT! You're outta here.

        throw new Unauthorized();
    }
}