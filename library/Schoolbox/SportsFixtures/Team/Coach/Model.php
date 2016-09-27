<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Team\Coach;

use \Schoolbox\SportsFixtures\Team\Model as Team;
use \Schoolbox\SportsFixtures\Coach\Model as Coach;

/**
 * Model for relation between teams and users.
 *
 * @package Schoolbox\SportsFixtures\Team\Coach
 */
class Model
{
    /**
     * Team part of the user/team relation.
     *
     * @var Team
     */
    protected $team;

    /**
     * Coach
     *
     * @var Coach
     */
    protected $coach;

    /**
     * Constructor - instantiate this object into minimum non-erroneous state.
     */
    public function __construct()
    {
        $this->team = new Team();
        $this->coach = new Coach();
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
     * @return Coach
     */
    public function getCoach()
    {
        return $this->coach;
    }

    /**
     * @param  Coach $coach
     * @return static
     */
    public function setCoach(Coach $coach)
    {
        $this->coach = $coach;
        return $this;
    }
}