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
use Schoolbox\Pastoral\SubType\Model as SubType;
use Schoolbox\Pastoral\Type\Model as Type;
use Schoolbox\Role;
use Schoolbox\User;

class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /** @var null|User */
    private $user;

    /** @var null|PastoralGroup */
    private $group;

    /** @var null|Role  */
    private $role;

    /**
     * The role type for this pastoral care access, if any.
     */
    private ?string $roleType = null;

    /** @var null|Type */
    private $type;

    /** @var null|SubType */
    private $subtype;

    /** @var integer */
    private $access;


    /**
     * Returns whether this AudienceRule is equal to some other AudienceRule
     *
     * @param self $other
     * @param bool $ignoreAccess If true, consider rules equal if they differ only by accessLevel
     * @return bool
     */
    public function equals(Model $other, bool $ignoreAccess = false): bool
    {
        // TODO remove explicit type-coercion once model hydration is fixed SA-4539
        if (($this->user xor $other->user) || ($this->user && (int)$this->user->getId() !== (int)$other->user->getId())) {
            return false;
        }

        if (($this->role xor $other->role) || ($this->role && (int)$this->role->getId() !== (int)$other->role->getId())) {
            return false;
        }

        if ($this->roleType !== $other->roleType) {
            return false;
        }

        if (($this->group xor $other->group) || ($this->group && (int)$this->group->getId() !== (int)$other->group->getId())) {
            return false;
        }

        if (($this->type xor $other->type) || ($this->type && (int)$this->type->getId() !== (int)$other->type->getId())) {
            return false;
        }

        if (($this->subtype xor $other->subtype) || ($this->subtype && (int)$this->subtype->getId() !== (int)$other->subtype->getId())) {
            return false;
        }

        return $ignoreAccess || $this->access === $other->access;
    }


    /**
     * @param PastoralGroup|null $pastoralGroup
     * @return $this
     */
    public function setGroup(?PastoralGroup $pastoralGroup): self
    {
        $this->group = $pastoralGroup;

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
}
