<?php

/**
 * IMetadataStrategy
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Team\Metadata\Strategy;

use Schoolbox\Collection;
use Schoolbox\Fixtures\Match\Model as Match;
use Schoolbox\Fixtures\Team\Model as Team;

interface IMetadataStrategy
{
    /**
     * Get all the Players for this Team
     *
     * @param Team $team
     * @return Collection
     */
    public function getAllPlayers(Team $team);

    /**
     * Get all the Positions used by Players for this Team
     *
     * @param Team $team
     * @return Collection
     */
    public function getAllPositions(Team $team);

    /**
     * Get all the Coaches used by this Team
     *
     * @param Team $team
     * @return Collection
     */
    public function getAllCoaches(Team $team);

    /**
     * Get the Match for which Players, Positions and Coaches
     * were accumulated
     *
     * @return Match
     */
    public function getMatch();
}
