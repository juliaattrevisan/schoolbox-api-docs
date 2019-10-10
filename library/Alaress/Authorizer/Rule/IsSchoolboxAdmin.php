<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Authorizer\Rule;

use Schoolbox\User;
use Alaress\Authorizer\Failure\Forbidden;

/**
 * Class IsSchoolboxAdmin
 *
 * Specifically isolates the Schoolbox Admin god-user.
 *
 * @package Alaress\Authorizer\Rule
 */
class IsSchoolboxAdmin implements IAuthorizationRule
{
    /**
     * Performs an authorization check.
     *
     * @param  array $params Any parameters passed as part of the route.
     * @param  User  $user   The user to authorize, if any. Null value assumes
     *                       that no user is logged in.
     * @throws \Alaress\Authorizer\Failure\Base if the request isn't authorized.
     */
    public function authorize(array $params = array(), User $user = null)
    {
        // ensure the user actually is a super, for starters
        (new IsSuperuser())->authorize($params, $user);

        if (!$user) {
            throw new Forbidden();
        }

        if ($user->getUsername() !== User::USERNAME_SCHOOLBOX_ADMIN) {
            throw new Forbidden();
        }

        $nameCorrect = $user->getFirstname() === 'Schoolbox' && $user->getLastname() === 'Admin';

        if (!$nameCorrect) {
            throw new Forbidden();
        }
    }

}
