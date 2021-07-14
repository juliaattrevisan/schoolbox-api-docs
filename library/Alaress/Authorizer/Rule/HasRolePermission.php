<?php

/**
 * Authorization method - verifies whether a user has a given role with given permission.
 *
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Authorizer\Rule;

use InvalidArgumentException;
use Schoolbox\Role;
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
    protected ?string $roleType;

    /**
     * @param string      $permission The permission to check
     * @param string|null $roleType   The role type to check. Matches one of the consts in Role
     * @throws InvalidArgumentException
     */
    public function __construct(string $permission, ?string $roleType)
    {
        if (!in_array($permission, Role::VALID_PERMISSIONS)) {
            throw new InvalidArgumentException('Invalid permission provided.');
        }

        $this->permission = $permission;
        $this->roleType   = $roleType;

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
            if ($this->roleType && ($user->getRole()->getType() !== $this->roleType)) {
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
