<?php
/**
 * Interface for authorization rules (for plugging into Alaress\Authorizer\Authorizer)
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Alaress\Authorizer\Rule;
use Schoolbox\User;

interface IAuthorizationRule {
    /**
     * Performs an authorization check.
     *
     * @param  User $user The user to authorize, if any. Null value assumes that
     *                    no user is logged in.
     * @throws \Alaress\Authorizer\Failure\Base if the request isn't authorized.
     */
    public function authorize(User $user = null);
}
