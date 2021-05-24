<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Team\Coach;

use Schoolbox\Fixtures\Team\Model as Team;
use Schoolbox\Fixtures\Coach\Model as Coach;

/**
 * Model for relation between teams and users.
 *
 * @package Schoolbox\Fixtures\Team\Coach
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
