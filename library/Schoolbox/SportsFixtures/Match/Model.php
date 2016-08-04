<?php
/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Match;

use Alaress_Date_Format;
use Schoolbox\ISoftDeletable;
use Schoolbox\Collection;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\SportsFixtures\Division\Model as Division;
use Schoolbox\SportsFixtures\Season\Model as Season;
use Schoolbox\SportsFixtures\Team\Model as Team;
use Schoolbox\SportsFixtures\Venue\Model as Venue;

class Model implements ISoftDeletable {
    use DeletedAtDate;

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

    /** @var int $awaySchoolId */
    protected $awaySchoolId;

    /** @var int $homeSchoolId */
    protected $homeSchoolId;

    /** @var string $notes */
    protected $notes;

    /** @var \Alaress_Date_Format */
    protected $startAt;

    /** @var \Alaress_Date_Format */
    protected $createdAt;

    /** @var \Alaress_Date_Format */
    protected $updatedAt;

    /** @var Collection */
    protected $result;

    /** @var Collection */
    protected $itinerary;

    /**
     * Model constructor.
     */
    public function __construct()
    {
        $this->setItinerary(new Collection());
        $this->setResult(new Collection());
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
    public function setId($id) {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId() {
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
     * @return int
     */
    public function getAwaySchoolId()
    {
        return $this->awaySchoolId;
    }

    /**
     * @param int $awaySchoolId
     * @return static
     */
    public function setAwaySchoolId($awaySchoolId)
    {
        $this->awaySchoolId = $awaySchoolId;
        return $this;
    }

    /**
     * @return int
     */
    public function getHomeSchoolId()
    {
        return $this->homeSchoolId;
    }

    /**
     * @param int $homeSchoolId
     * @return static
     */
    public function setHomeSchoolId($homeSchoolId)
    {
        $this->homeSchoolId = $homeSchoolId;
        return $this;
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
    public function getName() {
        return $this->name;
    }

    /**
     * @param string $name
     * @return static
     */
    public function setName($name) {
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
     * @return Collection
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @param Collection $result
     * @return static
     */
    public function setResult($result)
    {
        $this->result = $result;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getItinerary()
    {
        return $this->itinerary;
    }

    /**
     * @param Collection $itinerary
     * @return static
     */
    public function setItinerary($itinerary)
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

    /**
     * @param \Alaress_Date_Format $createdAt
     * @return static
     */
    public function setCreatedAt($createdAt) {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getCreatedAt() {
        return $this->createdAt;
    }

    /**
     * @param \Alaress_Date_Format $updatedAt
     * @return static
     */
    public function setUpdatedAt($updatedAt) {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getUpdatedAt() {
        return $this->updatedAt;
    }
}