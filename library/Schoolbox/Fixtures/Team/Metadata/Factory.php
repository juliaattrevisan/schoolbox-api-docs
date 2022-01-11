<?php

/**
 * Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Team\Metadata;

use Schoolbox\Fixtures\Team\Metadata\Strategy\Current as CurrentStrategy;
use Schoolbox\Fixtures\Team\Metadata\Strategy\Historic as HistoricStrategy;
use Schoolbox\Fixtures\Matchup\Model as Matchup;
use Schoolbox\UserMapper;
use Schoolbox\Fixtures\Team\Player\Mapper as TeamPlayerMapper;
use Schoolbox\Fixtures\Team\Position\Mapper as TeamPositionMapper;
use Schoolbox\Fixtures\Coach\Mapper as CoachMapper;
use Schoolbox\Fixtures\Team\Player\Participant\Mapper as TeamParticipantMapper;
use Schoolbox\Fixtures\Team\Position\Participant\Mapper as TeamPositionParticipantMapper;
use Schoolbox\Fixtures\Coach\Participant\Mapper as TeamCoachParticipantMapper;

class Factory
{
    /**
     * @var Matchup
     */
    private $matchup;

    /**
     * Factory constructor.
     *
     * @param Matchup $matchup
     */
    public function __construct(Matchup $matchup)
    {
        $this->setMatchup($matchup);
    }

    /**
     * If this is < 0, then the Matchup's start datetime is in the past
     * ie. it has already occurred
     *
     * @return Metadata
     */
    public function produce()
    {
        $diff = \Alaress_Date_Format::now()->diffInSeconds($this->getMatchup()->getStartAt(), false);

        if ($diff > 0) {
            $metadata = $this->produceCurrent();
        } else {
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
     * You can call this explicitly to bypass the Matchup Start check
     */
    public function produceCurrent()
    {
        return new Metadata($this->getMatchup(), new CurrentStrategy(
            $this->getMatchup(),
            UserMapper::getInstance(),
            TeamPlayerMapper::getInstance(),
            TeamPositionMapper::getInstance(),
            CoachMapper::getInstance()
        ));
    }

    /**
     * Produce a Metadata using the Historic Strategy
     *
     * You can call this explicitly to bypass the Matchup Start check
     * forcing Metadata to read from the snapshot tables
     */
    public function produceHistoric()
    {
        return new Metadata($this->getMatchup(), new HistoricStrategy(
            $this->getMatchup(),
            TeamParticipantMapper::getInstance(),
            TeamPositionParticipantMapper::getInstance(),
            TeamCoachParticipantMapper::getInstance()
        ));
    }

    /* Getters/Setters
     */

    /**
     * @return Matchup
     */
    private function getMatchup()
    {
        return $this->matchup;
    }

    /**
     * @param Matchup $matchup
     */
    private function setMatchup($matchup)
    {
        $this->matchup = $matchup;
    }
}
