<?php
/**
 * ParticipantModel
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\SportsFixtures\Coach\Participant;

use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\SportsFixtures\Match\Model as Match;
use Schoolbox\SportsFixtures\Team\Model as Team;
use Schoolbox\User;

/**
 * Snapshot of Team Coaches
 *
 * @package Schoolbox\SportsFixtures\Coach
 */
class Model
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
     * The user account (if any!) that this coach is associated with.
     *
     * @var User
     */
    protected $user;

    /**
     * The name of this coach.
     *
     * @var string
     */
    protected $name;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setUser(new User());
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
     * @param Team $team
     */
    public function setTeam($team)
    {
        $this->team = $team;
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
     * @return static
     */
    public function setName($name)
    {
        $this->name = $name;
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
     * @param User $user
     * @return static
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }
}
