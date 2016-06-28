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
use Schoolbox\User;
use \Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use \Schoolbox\SportsFixtures\Division\Model as Division;

class Model implements ISoftDeletable {
    use DeletedAtDate;

    /** @var int */
    protected $id;

    /** @var Division */
    protected $division;

    /** @var string */
    protected $name;

    /** @var \Alaress_Date_Format */
    protected $startAt;

    /** @var \Alaress_Date_Format */
    protected $createdAt;

    /** @var \Alaress_Date_Format */
    protected $updatedAt;

    /**
     * Model constructor.
     */
    public function __construct()
    {
    }

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
     * @return Division
     */
    public function getDivision()
    {
        return $this->division;
    }

    /**
     * @param Division $division
     */
    public function setDivision($division)
    {
        $this->division = $division;
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
     * @param \Alaress_Date_Format $createdAt
     * @return self
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
     * @return self
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