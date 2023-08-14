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

    public function setAccess(int $access): self;

    public function setMatchCampus(bool $matchCampus): self;

    public function setCommentsAllowed(bool $commentsAllowed): self;

    public function setRoleType(string $roleType): self;

    public function setRole(Role $role): self;

    public function setGroup(\Schoolbox\Pastoral\Group\Model $group): self;

    public function setUser(User $user): self;
}
