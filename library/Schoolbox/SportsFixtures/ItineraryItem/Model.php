<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\SportsFixtures\ItineraryItem;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use \Alaress_Date_Format;

/**
 * Represents a Position a player may take in some Sport.
 * Eg, 'Full Forward' or 'Full Back' in Aussie Rules Football
 *
 * For the time being, a participant may only be assigned 0 or 1 position
 *
 * @package Schoolbox\SportsFixtures\Position
 */
class Model implements ISoftDeletable
{
    // Implements ISoftDeletable
    use DeletedAtDate;

    /** @var int */
    protected $id;

    /** @var Match $match */
    protected $match;

    /** @var Venue $venue */
    protected $venue;

    /** @var string */
    protected $name;

    /** @var \Alaress_Date_Format */
    protected $startAt;

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
     * @return Venue
     */
    public function getVenue()
    {
        return $this->venue;
    }

    /**
     * @param Venue $venue
     */
    public function setVenue($venue)
    {
        $this->venue = $venue;
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
     */
    public function setStartAt($startAt)
    {
        $this->startAt = $startAt;
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
}