<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Division;

use Alaress_Date_Format;
use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\User;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Fixtures\Sport\Model as Sport;
use Schoolbox\Fixtures\Season\Model as Season;
use Schoolbox\Fixtures\Team;

class Model implements ISoftDeletable
{
    use DeletedAtDate;

    /** @var int */
    protected $id;

    /** @var Sport */
    protected $sport;

    /** @var Season */
    protected $season;

    /** @var string */
    protected $name;

    /** @var string */
    protected $url;

    /** @var Alaress_Date_Format */
    protected $createdAt;

    /** @var Alaress_Date_Format */
    protected $updatedAt;

    /**
     * The teams in this division.
     *
     * @var Collection|Team\Model[]
     */
    protected $teams;

    /**
     * The players in this division.
     *
     * @var Collection|User[]
     */
    protected $players;

    /**
     * Model constructor.
     */
    public function __construct()
    {
        $this->teams   = new Collection();
        $this->players = new Collection();
    }

    /**
     * Is this player in this division?
     *
     * @param User $player
     * @return bool
     */
    public function isPlayerInDivision(User $player)
    {
        foreach ($this->getPlayers() as $divisionPlayer) {
            if ($divisionPlayer->getId() == $player->getId()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Is this player in a team within this division?
     *
     * @param User $player
     * @return bool
     */
    public function isPlayerInTeam(User $player)
    {
        foreach ($this->getTeams() as $team) {
            if ($team->isPlayerInTeam($player)) {
                return true;
            }
        }
        return false;
    }

    /**
     * What teams within this division is this player in?
     *
     * @param User $player
     * @return Collection|Team\Model[]
     */
    public function getTeamsForPlayer(User $player)
    {
        $teams = new Collection();
        foreach ($this->getTeams() as $team) {
            if ($team->isPlayerInTeam($player)) {
                $teams->add($team);
            }
        }
        return $teams;
    }

    /* Getters/Setters
     */

    /**
     * @param int $id
     * @return self
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
     * @param string $name
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;
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
     * @param string $url
     * @return self
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param Sport $sport
     * @return self
     */
    public function setSport(Sport $sport)
    {
        $this->sport = $sport;
        return $this;
    }

    /**
     * @return Sport
     */
    public function getSport()
    {
        return $this->sport;
    }

    /**
     * @param Season $season
     * @return self
     */
    public function setSeason(Season $season)
    {
        $this->season = $season;
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
     * @param Alaress_Date_Format $createdAt
     * @return self
     */
    public function setCreatedAt(Alaress_Date_Format $createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return Alaress_Date_Format $createdAt
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param Alaress_Date_Format $updatedAt
     * @return self
     */
    public function setUpdatedAt(Alaress_Date_Format $updatedAt)
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection|Team\Model[]
     */
    public function getTeams()
    {
        return $this->teams;
    }

    /**
     * @param Collection|Team\Model[] $teams
     * @return static
     */
    public function setTeams($teams)
    {
        $this->teams = $teams;
        return $this;
    }

    /**
     * @return Collection|User[]
     */
    public function getPlayers()
    {
        return $this->players;
    }

    /**
     * @param Collection|User[] $players
     * @return static
     */
    public function setPlayers($players)
    {
        $this->players = $players;
        return $this;
    }
}
