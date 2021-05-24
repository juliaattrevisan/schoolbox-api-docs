<?php

/**
 * Interface for authorization rules (for plugging into Alaress\Authorizer\Authorizer)
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Authorizer\Rule;

use Schoolbox\User;

interface IAuthorizationRule
{
    /**
     * Performs an authorization check.
     *
     * @param array     $params Any parameters passed as part of the route.
     * @param User|null $user   The user to authorize, if any. Null value assumes
     *                          that no user is logged in.
     * @throws \Alaress\Authorizer\Failure\Base if the request isn't authorized.
     */
    public function authorize(array $params = array(), User $user = null);
}
