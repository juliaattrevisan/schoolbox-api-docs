<?php

/**
 * User relation object for Schoolbox.
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External;

use Schoolbox\User;

class UserRelation
{

    /**
     * Value for $this->relationType. (Currently, the only valid one.)
     *
     * States that $this->user is $this->relation's parent.
     *
     * @var int
     */
    const RELATION_TYPE_PARENT = 1;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var User
     */
    protected $relation;

    /**
     * @var int
     */
    protected $relationType;

    /**
     * @return User
     */
    public function getRelation()
    {
        return $this->relation;
    }

    /**
     * @param  User $relation
     * @return $this
     */
    public function setRelation($relation)
    {
        $this->relation = $relation;
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
     * @param  User $user
     * @return $this
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return int
     */
    public function getRelationType()
    {
        return $this->relationType;
    }

    /**
     * @param  int $relationType
     * @return $this
     */
    public function setRelationType($relationType)
    {
        $this->relationType = $relationType;
        return $this;
    }
}
