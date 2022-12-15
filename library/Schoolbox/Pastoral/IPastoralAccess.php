<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral;

use Schoolbox\Role;
use Schoolbox\User;

/**
 * Interface that defines a pastoral access class
 */
interface IPastoralAccess
{
    public function setId(int $id);

    public function setAccess(int $access);

    public function setCommentsAllowed(bool $commentsAllowed);

    public function setRoleType(string $roleType);

    public function setRole(Role $role);

    public function SetGroup(\Schoolbox\Pastoral\Group\Model $group);

    public function setUser(User $user);
}
