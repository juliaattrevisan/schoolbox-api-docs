<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Authorizer\Rule;

use Schoolbox\User;
use Alaress\Authorizer\Failure\Forbidden;

/**
 * Class IsAlaressAdmin
 *
 * Specifically isolates the Alaress Admin god-user.
 *
 * @package Alaress\Authorizer\Rule
 */
class IsAlaressAdmin implements IAuthorizationRule
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

        $nameCorrect = $user->getFirstname() === 'Alaress' && $user->getLastname() === 'Admin';

        if (!$nameCorrect) {
            throw new Forbidden();
        }
    }

}