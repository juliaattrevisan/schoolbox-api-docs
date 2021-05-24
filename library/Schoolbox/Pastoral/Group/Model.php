<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Group;

use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Pastoral\Group\Member\Model as PastoralGroupMember;
use Schoolbox\User;
use Schoolbox\ValueLabelInterface;

class Model implements ISoftDeletable, ValueLabelInterface
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /** @var string $name */
    protected $name;

    /**
     * Group creator
     *
     * @var User $user
     */
    protected $user;

    /** @var Collection|PastoralGroupMember[] $members */
    protected $members;

    /**
     * @param string $name
     *
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param User $user
     *
     * @return self
     */
    public function setUser(User $user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param Collection|PastoralGroupMember[] $members
     * @return self
     */
    public function setMembers(Collection $members)
    {
        $this->members = $members;

        return $this;
    }

    /**
     * @return Collection|PastoralGroupMember[]
     */
    public function getMembers()
    {
        return $this->members;
    }

    /**
     * Find a key/value representation of this model's data
     * and return it as a 2D array of value (an identifier)
     * and label (a human-readable description, usually)
     *
     * return [
     *     'value' => ...
     *     'label' => ...
     * ];
     *
     * @return array
     */
    public function findValueLabelPair(): array
    {
        return [
            'value' => $this->getId(),
            'label' => $this->getName()
        ];
    }
}
