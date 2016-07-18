<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Team\Metadata;

use \Schoolbox\Collection;
use \Schoolbox\UserMapper;
use \Schoolbox\SportsFixtures\Match\Model as Match;
use \Schoolbox\SportsFixtures\Team\Model as Team;

abstract class Base
{
    /**
     * @var Collection
     */
    protected $players;

    /**
     * @var Collection
     */
    protected $coaches;

    /**
     * @var Collection
     */
    protected $positions;

    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * Base constructor.
     *
     * @param Match $match
     */
    public function __construct(Match $match)
    {
        $this->setPlayers(new Collection());
        $this->setCoaches(new Collection());
        $this->setPositions(new Collection());
        $this->setUserMapper(UserMapper::getInstance());

        $this->collect($match);
    }

    /**
     * Collect team composition
     *
     * The players, their positions, and coaches
     *
     * @param Match $match
     */
    protected function collect(Match $match)
    {
        /** @var Team $homeTeam */
        /** @var Team $awayTeam */
        $homeTeam = $match->getHomeTeam();
        $awayTeam = $match->getAwayTeam();

        $this->collectForTeam($homeTeam);
        $this->collectForTeam($awayTeam);
    }

    abstract protected function collectForTeam(Team $team);

    /* Getters/Setters
     */

    /**
     * @return Collection
     */
    public function getPlayers()
    {
        return $this->players;
    }

    /**
     * @param Collection $players
     */
    protected function setPlayers($players)
    {
        $this->players = $players;
    }

    /**
     * @return Collection
     */
    public function getCoaches()
    {
        return $this->coaches;
    }

    /**
     * @param Collection $coaches
     */
    protected function setCoaches($coaches)
    {
        $this->coaches = $coaches;
    }

    /**
     * @return Collection
     */
    public function getPositions()
    {
        return $this->positions;
    }

    /**
     * @param Collection $positions
     */
    protected function setPositions($positions)
    {
        $this->positions = $positions;
    }

    /**
     * @return UserMapper
     */
    protected function getUserMapper()
    {
        return $this->userMapper;
    }

    /**
     * @param UserMapper $userMapper
     */
    protected function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
    }
}