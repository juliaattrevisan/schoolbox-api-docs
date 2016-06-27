<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Team\User;

use \Schoolbox\User as UserModel;
use \Schoolbox\SportsFixtures\Team\Model as TeamModel;

/**
 * Model for relation between teams and users.
 *
 * @package Schoolbox\SportsFixtures\Team\User
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
     * Team part of the user/team relation.
     *
     * @var TeamModel
     */
    protected $team;

    /**
     * User part of the user/team relation.
     *
     * @var UserModel
     */
    protected $user;

    /**
     * Constructor - instantiate this object into minimum non-erroneous state.
     */
    public function __construct()
    {
        $this->user = new UserModel();
        $this->team = new TeamModel();
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
     * @return TeamModel
     */
    public function getTeam()
    {
        return $this->team;
    }

    /**
     * @param  TeamModel $team
     * @return static
     */
    public function setTeam(TeamModel $team)
    {
        $this->team = $team;
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
}