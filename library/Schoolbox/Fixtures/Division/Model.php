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
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\User;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Fixtures\Sport\Model as Sport;
use Schoolbox\Fixtures\Season\Model as Season;
use Schoolbox\Fixtures\Team;

class Model implements ISoftDeletable
{
    use CreatedAtDate;
    use UpdatedAtDate;
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


    /**
     * The external identifier in the third party system
     * e.g. a Clipboard identifier, so we know where our
     * data came from
     *
     * @var string|null
     */
    private ?string $externalId = null;


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


    /**
     * @return string|null
     */
    public function getExternalId(): ?string
    {
        return $this->externalId;
    }


    /**
     * @param string|null $externalId
     * @return $this
     */
    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;
        return $this;
    }
}
