<?php
/**
 * This maintains an audit trail of every record a staff member views, and all the searches that they perform (and the
 * results that these searches returned)
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Audience;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Pastoral\Group\Model as PastoralGroup;
use Schoolbox\Pastoral\SubType\Model as SubType;
use Schoolbox\Pastoral\Type\Model as Type;

use Schoolbox\Role;
use Schoolbox\User;

class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use DeletedAtDate;

    /** @var User $user Schoolbox user whose actions we are tracking */
    private $user;

    /** @var null|PastoralGroup $pastoralGroup */
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
     * @param Type|null $type
     */
    public function setType(?Type $type): void
    {
        $this->type = $type;
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
     */
    public function setSubtype(?SubType $subtype): void
    {
        $this->subtype = $subtype;
    }


    /**
     * @return int
     */
    public function getTypeId(): int
    {
        return $this->type->getId();
    }


    /**
     * @param int $typeId
     * @return Model
     */
    public function setTypeId($typeId): self
    {
        if($this->type instanceof Type) {
            $this->type->setId($typeId);
        }
        else {
            $this->type = new Type();
            $this->type->setId($typeId);
        }

        return $this;
    }


    /**
     * @return int|null
     */
    public function getSubtypeId()
    {
        if($this->subtype instanceof Type) {
            return $this->subtype->getId();
        } else {
            return null;
        }

    }


    /**
     * @param int|null $subtypeId
     * @return Model
     */
    public function setSubtypeId($subtypeId): self
    {
        if($this->subtype instanceof Type) {
            $this->subtype->setId($subtypeId);
        }
        else {
            $this->subtype = new Type();
            $this->subtype->setId($subtypeId);
        }

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
