<?php
/**
 * IsPastoralModerator - Determine if the logged in user is a pastoral moderator
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
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null)
    {
        if ($user->getAccessList()->filterBy('service', UserAccess::SERVICE_PASTORAL)->count() > 0) {
            return true;
        }

        throw new Unauthorized();
    }
}