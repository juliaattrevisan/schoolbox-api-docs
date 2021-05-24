<?php

/**
 * This authorization method checks whether the user that is passed is a
 * superuser.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Authorizer\Rule;

use Schoolbox\User;
use Alaress\Authorizer\Failure\Unauthorized;

class IsSuperuser extends All
{
    /**
     * Constructor (obviously)
     */
    public function __construct()
    {
        parent::__construct(
            new IsLoggedIn(),
            new Callback(array($this, 'authorizeMethod'))
        );
    }


    /**
     * @param array $params
     * @param User  $user
     */
    public function authorizeMethod(array $params = array(), User $user = null)
    {
        if (!($user instanceof User && $user->getId() && $user->isSuperuser())) {
            throw new Unauthorized();
        }
    }
}
