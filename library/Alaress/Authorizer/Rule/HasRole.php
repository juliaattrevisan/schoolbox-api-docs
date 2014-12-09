<?php
/**
 * Authorization method - verifies whether a user has a given role.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Authorizer\Rule;

use Schoolbox\User;
use Alaress\Authorizer\Failure\Unauthorized;

class HasRole implements IAuthorizationRule
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
     * @param string $role The role to check.
     */
    public function __construct($role)
    {
        $this->role = $role;
    }

    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null)
    {
        if ($user instanceof User) {
            if ($user->getRole()->getType() == $this->role) {
                return;
            }
        }
        throw new Unauthorized();
    }
}
