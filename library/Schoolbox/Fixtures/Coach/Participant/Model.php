<?php

/**
 * ParticipantModel
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Coach\Participant;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\Fixtures\Matchup\Model as Matchup;
use Schoolbox\Fixtures\Team\Model as Team;
use Schoolbox\User;

/**
 * Snapshot of Team Coaches
 *
 * @package Schoolbox\Fixtures\Coach
 */
class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;

    /**
     * @var Matchup
     */
    protected $matchup;

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
     * @return Matchup
     */
    public function getMatchup()
    {
        return $this->matchup;
    }

    /**
     * @param Matchup $matchup
     */
    public function setMatchup($matchup)
    {
        $this->matchup = $matchup;
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
