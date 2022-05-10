<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Matchup;

use Alaress_Date_Format;
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

    /** @var Venue|null $venue */
    protected ?Venue $venue = null;

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

    /** @var Alaress_Date_Format */
    protected $finishAt;

    /** @var Result|null */
    protected $homeTeamResult;

    /** @var Result|null */
    protected $awayTeamResult;


    /** @var Collection|null */
    protected $itinerary;

    /**
     * Model constructor.
     */
    public function __construct()
    {
        $this->setItinerary(new Collection());
    }

    /**
     * Get the Team or School names for display purposes
     *
     * @return string
     */
    public function getDisplayName()
    {
        $homeName = 'Unknown';
        $awayName = 'Unknown';

        if (($this->getHomeTeam() instanceof Team) && ($this->getHomeTeam()->getId())) {
            $homeName = $this->getHomeTeam()->getName();
        } elseif (($this->getHomeSchool() instanceof School) && ($this->getHomeSchool()->getId())) {
            $homeName = $this->getHomeSchool()->getName();
        }

        if (($this->getAwayTeam() instanceof Team) && ($this->getAwayTeam()->getId())) {
            $awayName = $this->getAwayTeam()->getName();
        } elseif (($this->getAwaySchool() instanceof School) && ($this->getAwaySchool()->getId())) {
            $awayName = $this->getAwaySchool()->getName();
        }

        if (strlen($this->getName())) {
            $matchName = "{$this->getName()}; ";
        } else {
            $matchName = '';
        }

        return "{$matchName} {$homeName} vs {$awayName}";
    }

    /**
     * This Match contains $team
     *
     * @param Team $team
     * @return bool
     */
    public function hasTeam(Team $team)
    {
        $teamIds = [];
        if ($this->getHomeTeam() instanceof Team) {
            $teamIds[] = $this->getHomeTeam()->getId();
        }
        if ($this->getAwayTeam() instanceof Team) {
            $teamIds[] = $this->getAwayTeam()->getId();
        }
        return in_array($team->getId(), $teamIds);
    }

    /**
     * Returns a collection of all teams involved in match
     * @return Collection
     */
    public function getAllTeams()
    {
        $allTeams = new Collection();

        if ($this->getHomeTeam() instanceof Team) {
            $allTeams->add($this->getHomeTeam());
        }
        if ($this->getAwayTeam() instanceof Team) {
            $allTeams->add($this->getAwayTeam());
        }
        return $allTeams;
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
     * @return Team|null
     */
    public function getHomeTeam()
    {
        return $this->homeTeam;
    }

    /**
     * @param Team|null $homeTeam
     * @return static
     */
    public function setHomeTeam(Team $homeTeam = null)
    {
        $this->homeTeam = $homeTeam;
        return $this;
    }

    /**
     * @return Team|null
     */
    public function getAwayTeam()
    {
        return $this->awayTeam;
    }

    /**
     * @param Team|null $awayTeam
     * @return static
     */
    public function setAwayTeam(Team $awayTeam = null)
    {
        $this->awayTeam = $awayTeam;
        return $this;
    }

    /**
     * @return School|null
     */
    public function getAwaySchool()
    {
        return $this->awaySchool;
    }

    /**
     * @param School|null $awaySchool
     * @return static
     */
    public function setAwaySchool(School $awaySchool = null)
    {
        $this->awaySchool = $awaySchool;
        return $this;
    }

    /**
     * @return School|null
     */
    public function getHomeSchool()
    {
        return $this->homeSchool;
    }

    /**
     * @param School|null $homeSchool
     * @return static
     */
    public function setHomeSchool(School $homeSchool = null)
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
    public function setHomeTeamResult(?Result $result)
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
    public function setAwayTeamResult(?Result $result)
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
     * @return Venue|null
     */
    public function getVenue()
    {
        return $this->venue;
    }

    /**
     * @param Venue|null $venue
     * @return $this
     */
    public function setVenue(?Venue $venue): self
    {
        $this->venue = $venue;
        return $this;
    }


    /**
     * @return Collection|null
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
     * @return Alaress_Date_Format|null
     */
    public function getFinishAt(): ?Alaress_Date_Format
    {
        return $this->finishAt;
    }


    /**
     * @param Alaress_Date_Format $finishAt
     * @return Model
     */
    public function setFinishAt(Alaress_Date_Format $finishAt): self
    {
        $this->finishAt = $finishAt;
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
