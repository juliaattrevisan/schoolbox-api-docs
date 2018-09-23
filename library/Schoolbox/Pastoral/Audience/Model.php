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

use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Pastoral\Group\Model as PastoralGroup;
use Schoolbox\Role;
use Schoolbox\User;

class Model
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

    /** @var integer */
    private $typeId;

    /** @var null|integer */
    private $subtypeId;

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
     * @return int
     */
    public function getTypeId(): int
    {
        return $this->typeId;
    }


    /**
     * @param int $typeId
     * @return Model
     */
    public function setTypeId($typeId): self
    {
        $this->typeId = $typeId;

        return $this;
    }


    /**
     * @return int|null
     */
    public function getSubtypeId()
    {
        return $this->subtypeId;
    }


    /**
     * @param int|null $subtypeId
     * @return Model
     */
    public function setSubtypeId($subtypeId): self
    {
        $this->subtypeId = $subtypeId;

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
