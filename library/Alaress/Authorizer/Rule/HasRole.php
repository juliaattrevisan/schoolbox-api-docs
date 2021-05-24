<?php

/**
 * Authorization method - verifies whether a user has a given role.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Authorizer\Rule;

use Schoolbox\User;
use Alaress\Authorizer\Failure\Unauthorized;

class HasRole extends All
{

    /**
     * The role to check against.
     *
     * One of Schoolbox\Role::(GUEST|STAFF|PARENT|STUDENT).
     *
     * @var string
     */
    protected $role;


    /**
     * @param string $role The role to check. Matches one of the consts in Role
     * @throws \InvalidArgumentException
     */
    public function __construct(string $role)
    {
        $this->role = $role;

        parent::__construct(
            new IsLoggedIn(),
            new Callback(array($this, 'authorizeMethod'))
        );
    }

    /**
     * {@inheritdoc}
     */
    public function authorizeMethod(array $params = array(), User $user = null)
    {
        if ($user instanceof User) {
            if ($user->getRole()->getType() == $this->role) {
                return;
            }
        }
        throw new Unauthorized();
    }
}
