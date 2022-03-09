<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\Fixtures;

use Calendar\Event\Type\Day;

/**
 * This data is rendered by a Renderer class to display the data
 * in a variety of formats
 */
class Event extends Day\Event
{

    /**
     * @var \Schoolbox\Fixtures\Matchup\Model|null
     */
    private ?\Schoolbox\Fixtures\Matchup\Model $matchup = null;


    /**
     * A list of team identifiers that a user is involved with
     *
     * @var array
     */
    private array $involvedTeamIds = [];


    /**
     * @return string[]
     */
    protected function getRenderClasses(): array
    {
        return [
            self::RENDER_FORMAT_JSON => Renderer\Javascript::class,
            self::RENDER_FORMAT_VCAL => Renderer\VObject::class,
            self::RENDER_FORMAT_CSV  => Renderer\Csv::class,
        ];
    }


    /**
     * @return \Schoolbox\Fixtures\Matchup\Model|null
     */
    public function getMatchup(): ?\Schoolbox\Fixtures\Matchup\Model
    {
        return $this->matchup;
    }


    /**
     * @param \Schoolbox\Fixtures\Matchup\Model|null $matchup
     * @return Event
     */
    public function setMatchup(?\Schoolbox\Fixtures\Matchup\Model $matchup): self
    {
        $this->matchup = $matchup;
        return $this;
    }


    /**
     * @return array
     */
    public function getInvolvedTeamIds(): array
    {
        return $this->involvedTeamIds;
    }


    /**
     * @param array $involvedTeamIds
     * @return Event
     */
    public function setInvolvedTeamIds(array $involvedTeamIds): self
    {
        $this->involvedTeamIds = $involvedTeamIds;
        return $this;
    }
}
