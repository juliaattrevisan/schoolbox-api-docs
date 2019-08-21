<?php
/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Team;

use \Alaress_Date_Format;
use \Schoolbox\Collection;
use \Schoolbox\ISoftDeletable;
use \Schoolbox\User;
use \Schoolbox\ValueLabelInterface;
use \Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use \Schoolbox\Fixtures\Coach;
use \Schoolbox\Fixtures\Division\Model as Division;

class Model implements ISoftDeletable, ValueLabelInterface {
    use DeletedAtDate;

    /** @var int */
    protected $id;

    /** @var Division */
    protected $division;

    /** @var string */
    protected $name;

    /** @var string */
    protected $url;

    /** @var Alaress_Date_Format */
    protected $createdAt;

    /** @var Alaress_Date_Format */
    protected $updatedAt;

    /**
     * Players within the team.
     *
     * @var Collection|User[]
     */
    protected $players;

    /**
     * Coaches within the team.
     *
     * @var Collection|Coach\Model[]
     */
    protected $coaches;

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function findValueLabelPair()
    {
        return [
            'value' => $this->getId(),
            'label' => $this->getName()
        ];
    }

    /**
     * Is this player in this team?
     *
     * @param User $player
     * @return bool
     */
    public function isPlayerInTeam(User $player)
    {
        foreach ($this->getPlayers() as $teamPlayer) {
            if ($teamPlayer->getId() == $player->getId()) {
                return true;
            }
        }
        return false;
    }

    /* Getters/Setters
     */

    /**
     * @param int $id
     * @return self
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
     * @param string $name
     * @return self
     */
    public function setName($name) {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @param string $url
     * @return self
     */
    public function setUrl($url) {
        $this->url = $url;
        return $this;
    }

    /**
     * @return string
     */
    public function getUrl() {
        return $this->url;
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
     * @return $this
     */
    public function setDivision($division)
    {
        $this->division = $division;
        return $this;
    }

    /**
     * @param Alaress_Date_Format $createdAt
     * @return self
     */
    public function setCreatedAt($createdAt) {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return Alaress_Date_Format $createdAt
     */
    public function getCreatedAt() {
        return $this->createdAt;
    }

    /**
     * @param Alaress_Date_Format $updatedAt
     * @return self
     */
    public function setUpdatedAt($updatedAt) {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getUpdatedAt() {
        return $this->updatedAt;
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
     * @return Collection|Coach\Model[]
     */
    public function getCoaches()
    {
        return $this->coaches;
    }

    /**
     * @param Collection|Coach\Model[] $coaches
     * @return static
     */
    public function setCoaches($coaches)
    {
        $this->coaches = $coaches;
        return $this;
    }
}
