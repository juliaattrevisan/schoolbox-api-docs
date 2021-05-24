<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Division\User;

use Schoolbox\User as User;
use Schoolbox\Fixtures\Division\Model as Division;

/**
 * Model for relation between divisions and users.
 *
 * @package Schoolbox\Fixtures\Division\User
 */
class Model
{
    /**
     * Auto-incremented ID
     *
     * @var int
     */
    protected $id;

    /**
     * Division part of the user/division relation.
     *
     * @var Division
     */
    protected $division;

    /**
     * User part of the user/division relation.
     *
     * @var User
     */
    protected $user;

    /**
     * Order of this user within the division (higher value = higher rank)
     *
     * @var int
     */
    protected $sequence;

    /**
     * Constructor - instantiate this object into minimum non-erroneous state.
     */
    public function __construct()
    {
        $this->user     = new User();
        $this->division = new Division();
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param  int $id
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return Division
     */
    public function getDivision()
    {
        return $this->division;
    }

    /**
     * @param  Division $division
     * @return static
     */
    public function setDivision(Division $division)
    {
        $this->division = $division;
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
     * @return static
     */
    public function setUser(User $user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return int
     */
    public function getSequence()
    {
        return $this->sequence;
    }

    /**
     * @param  int $sequence
     * @return static
     */
    public function setSequence($sequence)
    {
        $this->sequence = $sequence;
        return $this;
    }
}
