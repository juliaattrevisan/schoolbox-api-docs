<?php

/**
 * ParticipantModel
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Team\Position\Participant;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\User;
use Schoolbox\Fixtures\Match\Model as Match;
use Schoolbox\Fixtures\Team\Model as Team;

/**
 * Model for relation between teams and users.
 *
 * @package Schoolbox\Fixtures\Team\Player\Participant
 */
class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;

    /**
     * @var Match
     */
    protected $match;

    /**
     * @var Team
     */
    protected $team;

    /**
     * @var User
     */
    protected $user;

    /**
     * The Position Name
     * Denormalised in case someone renames/repurposes original record
     *
     * @var string
     */
    protected $name;

    /**
     * Constructor - instantiate this object into minimum non-erroneous state.
     */
    public function __construct()
    {
        $this->setUser(new User());
        $this->setTeam(new Team());
    }

    /* Getters/Setters
     */

    /**
     * @return Match
     */
    public function getMatch()
    {
        return $this->match;
    }

    /**
     * @param Match $match
     */
    public function setMatch($match)
    {
        $this->match = $match;
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
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
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
