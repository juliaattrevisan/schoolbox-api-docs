<?php
/**
 * This authorization method checks whether a user is passed.
 *
 * Its main use is to check whether a user is even logged in.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthenticated;
use Schoolbox\User;

class IsLoggedIn implements IAuthorizationRule
{

    /**
     * @param array $params
     * @param User  $user
     */
    public function authorize(array $params = array(), User $user = null)
    {
        // True:  skip to next item in chain
        // False: break chain with request that redirects somewhere
        // These rules should define what to do on failure too.
        if (!($user instanceof User && $user->getId())) {
            throw new Unauthenticated();
        }
    }
}
