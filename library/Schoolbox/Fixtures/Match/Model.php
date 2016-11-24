<?php
/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Fixtures\Match;

use \Alaress_Date_Format;
use Schoolbox\ISoftDeletable;
use Schoolbox\Collection;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\Fixtures\Division\Model as Division;
use Schoolbox\Fixtures\Season\Model as Season;
use Schoolbox\Fixtures\Team\Model as Team;
use Schoolbox\Fixtures\Venue\Model as Venue;
use Schoolbox\Fixtures\Result\Model as Result;
use Schoolbox\Fixtures\ItineraryItem\Model as ItineraryItem;
use Schoolbox\Fixtures\School\Model as School;

class Model implements ISoftDeletable
{
    use ModelTrait\DeletedAtDate;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;

    /** @var int */
    protected $id;

    /** @var Division */
    protected $division;

    /** @var Season */
    protected $season;

    /** @var string */
    protected $name;

    /** @var Venue $venue */
    protected $venue;

    /** @var Team $homeTeam */
    protected $homeTeam;

    /** @var Team $awayTeam */
    protected $awayTeam;

    /** @var School $awaySchool */
    protected $awaySchool;

    /** @var School $homeSchool */
    protected $homeSchool;

    /** @var string $notes */
    protected $notes;

    /** @var Alaress_Date_Format */
    protected $startAt;

    /** @var Result|null */
    protected $homeTeamResult;

    /** @var Result|null */
    protected $awayTeamResult;


    /** @var Collection|ItineraryItem[] */
    protected $itinerary;

    /**
     * Model constructor.
     */
    public function __construct()
    {
        $this->setItinerary(new Collection());
    }


    /**
     * This Match contains $team
     *
     * @param Team $team
     * @return bool
     */
    public function hasTeam(Team $team)
    {
        return in_array($team->getId(), [$this->getHomeTeam()->getId(), $this->getAwayTeam()->getId()]);
    }

    /* Getters/Setters
     */

    /**
     * @param int $id
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return Team
     */
    public function getHomeTeam()
    {
        return $this->homeTeam;
    }

    /**
     * @param Team $homeTeam
     * @return static
     */
    public function setHomeTeam(Team $homeTeam)
    {
        $this->homeTeam = $homeTeam;
        return $this;
    }

    /**
     * @return Team
     */
    public function getAwayTeam()
    {
        return $this->awayTeam;
    }

    /**
     * @param Team $awayTeam
     * @return static
     */
    public function setAwayTeam(Team $awayTeam)
    {
        $this->awayTeam = $awayTeam;
        return $this;
    }

    /**
     * @return School
     */
    public function getAwaySchool()
    {
        return $this->awaySchool;
    }

    /**
     * @param School $awaySchool
     * @return static
     */
    public function setAwaySchool(School $awaySchool)
    {
        $this->awaySchool = $awaySchool;
        return $this;
    }

    /**
     * @return School
     */
    public function getHomeSchool()
    {
        return $this->homeSchool;
    }

    /**
     * @param School $homeSchool
     * @return static
     */
    public function setHomeSchool($homeSchool)
    {
        $this->homeSchool = $homeSchool;
        return $this;
    }

    /**
     * Returns scalar null if home team is a school, or if no result yet
     * @return null|Result
     */
    public function getHomeTeamResult()
    {
        return $this->homeTeamResult;
    }

    /**
     * @param null|Result $result
     * @return static
     */
    public function setHomeTeamResult(Result $result)
    {
        $this->homeTeamResult = $result;
        return $this;
    }

    /**
     * Returns scalar null if away team is a school, or if no result yet
     * @return null|Result
     */
    public function getAwayTeamResult()
    {
        return $this->awayTeamResult;
    }

    /**
     * @param null|Result $result
     * @return static
     */
    public function setAwayTeamResult(Result $result)
    {
        $this->awayTeamResult = $result;
        return $this;
    }


    /**
     * @return Collection|Result[] Indexed by team_id
     */
    public function getResults()
    {
        $results = new Collection();
        if ($this->getHomeTeamResult()) {
            $results[$this->getHomeTeamResult()->getTeam()->getId()] = $this->getHomeTeamResult();
        }
        if ($this->getAwayTeamResult()) {
            $results[$this->getAwayTeamResult()->getTeam()->getId()] = $this->getAwayTeamResult();
        }
        return $results;
    }

    /**
     * For robustness, we do not permit results to be set as a Collection.
     * This is to prevent callers attempting to set results for Teams not attached to this match,
     * which would lead to indeterminate state and behaviour.
     *
     * @param Collection|Result[] $results
     */
    public function setResults(Collection $results)
    {
        throw new \DomainException('Results must be set per team.');
    }

    /**
     * @return Division
     */
    public function getDivision()
    {
        return $this->division;
    }

    /**
     * @param Division $division
     * @return static
     */
    public function setDivision($division)
    {
        $this->division = $division;
        return $this;
    }

    /**
     * @return Season
     */
    public function getSeason()
    {
        return $this->season;
    }

    /**
     * @param Season $season
     * @return static
     */
    public function setSeason(Season $season)
    {
        $this->season = $season;
        return $this;
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
     * @return Venue
     */
    public function getVenue()
    {
        return $this->venue;
    }

    /**
     * @param Venue $venue
     * @return static
     */
    public function setVenue($venue)
    {
        $this->venue = $venue;
        return $this;
    }


    /**
     * @return Collection|ItineraryItem[]
     */
    public function getItinerary()
    {
        return $this->itinerary;
    }

    /**
     * @param Collection|ItineraryItem[] $itinerary
     * @return static
     */
    public function setItinerary(Collection $itinerary)
    {
        $this->itinerary = $itinerary;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getStartAt()
    {
        return $this->startAt;
    }

    /**
     * @param Alaress_Date_Format $startAt
     * @return static
     */
    public function setStartAt($startAt)
    {
        $this->startAt = $startAt;
        return $this;
    }

    /**
     * @return string
     */
    public function getNotes()
    {
        return $this->notes;
    }

    /**
     * @param string $notes
     * @return static
     */
    public function setNotes($notes)
    {
        $this->notes = $notes;
        return $this;
    }
}
