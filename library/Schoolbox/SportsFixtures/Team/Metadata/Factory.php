<?php
/**
 * Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Team\Metadata;

use \Schoolbox\SportsFixtures\Team\Metadata\Strategy\Current as CurrentStrategy;
use \Schoolbox\SportsFixtures\Team\Metadata\Strategy\Historic as HistoricStrategy;
use \Schoolbox\SportsFixtures\Match\Model as Match;

use \Schoolbox\UserMapper;
use \Schoolbox\SportsFixtures\Team\Player\Mapper as TeamPlayerMapper;
use \Schoolbox\SportsFixtures\Team\Position\Mapper as TeamPositionMapper;
use \Schoolbox\SportsFixtures\Coach\Mapper as CoachMapper;

use \Schoolbox\SportsFixtures\Team\Player\Participant\Mapper as TeamParticipantMapper;
use \Schoolbox\SportsFixtures\Team\Position\Participant\Mapper as TeamPositionParticipantMapper;
use \Schoolbox\SportsFixtures\Coach\Participant\Mapper as TeamCoachParticipantMapper;

class Factory
{
    /**
     * @var Match
     */
    private $match;

    /**
     * Factory constructor.
     *
     * @param $match
     */
    public function __construct(Match $match)
    {
        $this->setMatch($match);
    }

    /**
     * If this is < 0, then the Match's start datetime is in the past
     * ie. it has already occurred
     *
     * @return Metadata
     */
    public function produce()
    {
        $diff = \Alaress_Date_Format::now()->diffInSeconds($this->getMatch()->getStartAt(), false);

        if ($diff > 0) {
            $metadata = $this->produceCurrent();
        }
        else {
            $metadata = $this->produceHistoric();
        }

        /* Jump start the Metadata with collect();
         *
         * It's pretty useless without it...and doing it
         * here saves you having to do it everywhere else
         */
        $metadata->collect();

        return $metadata;
    }

    /**
     * Produce a Metadata using the Current Strategy
     *
     * You can call this explicitly to bypass the Match Start check
     */
    public function produceCurrent()
    {
        return new Metadata($this->getMatch(), new CurrentStrategy(
            $this->getMatch(),
            UserMapper::getInstance(),
            TeamPlayerMapper::getInstance(),
            TeamPositionMapper::getInstance(),
            CoachMapper::getInstance()
        ));
    }

    /**
     * Produce a Metadata using the Historic Strategy
     *
     * You can call this explicitly to bypass the Match Start check
     * forcing Metadata to read from the snapshot tables
     */
    public function produceHistoric()
    {
        return new Metadata($this->getMatch(), new HistoricStrategy(
            $this->getMatch(),
            TeamParticipantMapper::getInstance(),
            TeamPositionParticipantMapper::getInstance(),
            TeamCoachParticipantMapper::getInstance()
        ));
    }

    /* Getters/Setters
     */

    /**
     * @return Match
     */
    private function getMatch()
    {
        return $this->match;
    }

    /**
     * @param Match $match
     */
    private function setMatch($match)
    {
        $this->match = $match;
    }
}