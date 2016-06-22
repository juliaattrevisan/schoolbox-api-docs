<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Division\User;

use \Schoolbox\User as UserModel;
use \Schoolbox\SportsFixtures\Division\Model as DivisionModel;

/**
 * Model for relation between divisions and users.
 *
 * @package Schoolbox\SportsFixtures\Division\User
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
     * @var DivisionModel
     */
    protected $division;

    /**
     * User part of the user/division relation.
     *
     * @var UserModel
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
        $this->user     = new UserModel();
        $this->division = new DivisionModel();
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
     * @return DivisionModel
     */
    public function getDivision()
    {
        return $this->division;
    }

    /**
     * @param  DivisionModel $division
     * @return static
     */
    public function setDivision(DivisionModel $division)
    {
        $this->division = $division;
        return $this;
    }

    /**
     * @return UserModel
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param  UserModel $user
     * @return static
     */
    public function setUser(UserModel $user)
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