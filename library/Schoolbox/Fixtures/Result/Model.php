<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Result;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Alaress_Date_Format;
use Schoolbox\Fixtures\Match\Model as Match;
use Schoolbox\Fixtures\Team\Model as Team;
use Schoolbox\Fixtures\Status\Model as Status;

/**
 * A Result for a particular Match
 *
 * @package Schoolbox\Fixtures\Result
 */
class Model implements ISoftDeletable
{
    // Implements ISoftDeletable
    use DeletedAtDate;

    /** @var int */
    protected $id;

    /** @var Match $match */
    protected $match;

    /** @var Team $team */
    protected $team;

    /** @var Status $status */
    protected $status;

    /** @var string */
    protected $description;

    /** @var Alaress_Date_Format */
    protected $createdAt;

    /** @var Alaress_Date_Format */
    protected $updatedAt;

    /**
     * Model constructor.
     */
    public function __construct()
    {
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
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return Team
     */
    public function getTeam()
    {
        return $this->team;
    }

    /**
     * @param Team $team
     */
    public function setTeam($team)
    {
        $this->team = $team;
    }

    /**
     * @return Match
     */
    public function getMatch()
    {
        return $this->match;
    }

    /**
     * @param Match $match
     */
    public function setMatch($match)
    {
        $this->match = $match;
    }

    /**
     * @return Status
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param Status $status
     * @return static
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @param Alaress_Date_Format $createdAt
     * @return static
     */
    public function setCreatedAt($createdAt)
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
     * @return static
     */
    public function setUpdatedAt($updatedAt)
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
}
