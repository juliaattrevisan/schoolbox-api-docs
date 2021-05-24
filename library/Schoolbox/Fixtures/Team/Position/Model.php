<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Team\Position;

use Schoolbox\User as User;
use Schoolbox\Fixtures\Team\Model as Team;
use Schoolbox\Fixtures\Position\Model as Position;

/**
 * Model for relation between teams and users.
 *
 * @package Schoolbox\Fixtures\Team\User
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
     * @var Team
     */
    protected $team;

    /**
     * User part of the user/team relation.
     *
     * @var User
     */
    protected $user;

    /**
     * Position of player
     *
     * @var Position
     */
    protected $position;

    /**
     * Constructor - instantiate this object into minimum non-erroneous state.
     */
    public function __construct()
    {
        $this->user = new User();
        $this->team = new Team();
        $this->position = new Position();
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
     * @return Team
     */
    public function getTeam()
    {
        return $this->team;
    }

    /**
     * @param  Team $team
     * @return static
     */
    public function setTeam(Team $team)
    {
        $this->team = $team;
        return $this;
    }

    /**
     * @return Position
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * @param  Position $position
     * @return static
     */
    public function setPosition(Position $position)
    {
        $this->position = $position;
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
}
