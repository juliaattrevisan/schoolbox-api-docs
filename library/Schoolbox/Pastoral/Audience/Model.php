<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
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

    /** @var User */
    private $user;

    /** @var null|PastoralGroup */
    private $group;

    /** @var null|Role  */
    private $role;

    /** @var null|Type */
    private $type;

    /** @var null|SubType */
    private $subtype;

    /** @var integer */
    private $access;


    /**
     * @param PastoralGroup|null $pastoralGroup
     * @return $this
     */
    public function setGroup(PastoralGroup $pastoralGroup): self
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
     * @param User $user
     *
     * @return self
     */
    public function setUser(User $user): self
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
     * @param Role $role
     * @return Model
     */
    public function setRole($role): self
    {
        $this->role = $role;

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
     * @param Type $type
     * @return $this
     */
    public function setType(Type $type): self
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
     * @param SubType|null $subtype
     * @return $this
     */
    public function setSubtype(?SubType $subtype): self
    {
        $this->subtype = $subtype;
        return $this;
    }

    /**
     * @return int
     */
    public function getAccess(): int
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
