<?php
/**
 * IMetadataStrategy
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Team\Metadata\Strategy;

use \Schoolbox\Collection;
use \Schoolbox\SportsFixtures\Team\Model as Team;

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
}