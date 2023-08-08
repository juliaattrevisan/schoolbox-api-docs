<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Audience;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Pastoral\Group\Model as PastoralGroup;
use Schoolbox\Pastoral\IPastoralAccess;
use Schoolbox\Pastoral\SubType\Model as SubType;
use Schoolbox\Pastoral\Type\Model as Type;
use Schoolbox\Role;
use Schoolbox\User;

class Model implements ISoftDeletable, IPastoralAccess
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /** @var null|User */
    private ?User $user = null;

    /** @var null|PastoralGroup */
    private ?PastoralGroup $group = null;

    /** @var null|Role  */
    private ?Role $role = null;

    /**
     * The role type for this pastoral care access, if any.
     */
    private ?string $roleType = null;

    /** @var null|Type */
    private ?Type $type = null;

    /** @var null|SubType */
    private ?SubType $subtype = null;

    /** @var integer */
    private int $access;

    /**
     * Whether role / group access is limited to users in student campus only
     * @var bool
     */
    private bool $studentCampusOnly = false;

    /**
     * Whether the user/role is allowed to comment
     * Staff with access can always comment regardless of this flag
     */
    private bool $commentsAllowed = false;


    /**
     * @param PastoralGroup|null $group
     * @return $this
     */
    public function setGroup(?PastoralGroup $group): self
    {
        $this->group = $group;

        return $this;
    }

    /**
     * @return null|PastoralGroup
     */
    public function getGroup(): ?PastoralGroup
    {
        return $this->group;
    }

    /**
     * @param null|User $user
     *
     * @return self
     */
    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }


    /**
     * @return Role|null
     */
    public function getRole(): ?Role
    {
        return $this->role;
    }


    /**
     * @param null|Role $role
     * @return $this
     */
    public function setRole(?Role $role): self
    {
        $this->role = $role;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getRoleType(): ?string
    {
        return $this->roleType;
    }

    /**
     * @param string|null $roleType
     * @return $this
     */
    public function setRoleType(?string $roleType): self
    {
        $this->roleType = $roleType;
        return $this;
    }

    /**
     * @return Type|null
     */
    public function getType(): ?Type
    {
        return $this->type;
    }

    /**
     * @param null|Type $type
     * @return $this
     */
    public function setType(?Type $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return SubType|null
     */
    public function getSubtype(): ?SubType
    {
        return $this->subtype;
    }

    /**
     * @param null|SubType $subtype
     * @return $this
     */
    public function setSubtype(?SubType $subtype): self
    {
        $this->subtype = $subtype;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getAccess(): ?int
    {
        return $this->access;
    }

    /**
     * @param int $access
     * @return Model
     */
    public function setAccess(int $access): self
    {
        $this->access = $access;
        return $this;
    }

    /**
     * @return bool
     */
    public function getStudentCampusOnly(): bool
    {
        return $this->studentCampusOnly;
    }

    /**
     * @param bool $studentCampusOnly
     * @return static
     */
    public function setStudentCampusOnly(bool $studentCampusOnly): self
    {
        $this->studentCampusOnly = $studentCampusOnly;
        return $this;
    }

    /**
     * @return bool
     */
    public function getCommentsAllowed(): bool
    {
        return $this->commentsAllowed;
    }

    /**
     * @param bool $commentsAllowed
     * @return static
     */
    public function setCommentsAllowed(bool $commentsAllowed): self
    {
        $this->commentsAllowed = $commentsAllowed;
        return $this;
    }
}
