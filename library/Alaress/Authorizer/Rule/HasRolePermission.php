<?php

/**
 * Authorization method - verifies whether a user has a given role with given permission.
 *
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Authorizer\Rule;

use Schoolbox\User;
use Alaress\Authorizer\Failure\Unauthorized;

class HasRolePermission extends All
{

    /**
     * The permission to check against.
     */
    protected string $permission;

    /**
     * The role to check against (optional).
     * One of Schoolbox\Role::(GUEST|STAFF|PARENT|STUDENT).
     */
    protected ?string $role;

    /**
     * @param string      $permission The permission to check
     * @param string|null $role       The role to check. Matches one of the consts in Role
     * @throws \InvalidArgumentException
     */
    public function __construct(string $permission, ?string $role)
    {
        $this->permission = $permission;
        $this->role       = $role;

        parent::__construct(
            new IsLoggedIn(),
            new Callback([$this, 'authorizeMethod'])
        );
    }

    /**
     * @param array     $params
     * @param User|null $user
     */
    public function authorizeMethod(array $params = [], User $user = null)
    {
        if ($user instanceof User) {
            if ($this->role && ($user->getRole()->getType() !== $this->role)) {
                throw new Unauthorized();
            }
        }
        $permissionMethod = 'get' . ucfirst($this->permission);
        if (method_exists($user->getRole(), $permissionMethod) && $user->getRole()->$permissionMethod()) {
            return;
        }
        throw new Unauthorized();
    }
}
