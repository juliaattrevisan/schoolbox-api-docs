<?php
namespace Schoolbox\SportsFixtures\Sport;

use Alaress_Date_Format;
use Schoolbox\ISoftDeletable;
use Schoolbox\User;

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Model implements ISoftDeletable {
    /** @var int */
    protected $id;
    /** @var string */
    protected $name;
    /** @var string */
    protected $homepage;
    /** @var int */
    //protected $year;
    /** @var int */
    //protected $semester;
    /** @var boolean */
    protected $resultsIndividual;
    /** @var \Alaress_Date_Format */
    protected $createdAt;
    /** @var \Alaress_Date_Format */
    protected $updatedAt;
    /** @var \Alaress_Date_Format */
    protected $deletedAt;
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
     * @param string $homepage
     * @return self
     */
    public function setHomepage($homepage) {
        $this->homepage = $homepage;
        return $this;
    }
    /**
     * @return string
     */
    public function getHomepage() {
        return $this->homepage;
    }
    /**
     * @param int $year
     * @return self
     */
    /*    public function setYear($year) {
            $this->year = $year;
            return $this;
        }
    */
    /**
     * @return int
     */
    /*  public function getYear() {
          return $this->year;
      }
  */
    /**
     * @param int $semester
     * @return self
     */
    /*  public function setSemester($semester) {
          $this->semester = $semester;
          return $this;
      }
  */
    /**
     * @return int
     */
    /*  public function getSemester() {
          return $this->semester;
      }
  */
    /**
     * @param boolean $resultsIndividual
     * @return self
     */
    public function setResultsIndividual($resultsIndividual) {
        $this->resultsIndividual = $resultsIndividual;
        return $this;
    }
    /**
     * @return boolean
     */
    public function getResultsIndividual() {
        return $this->resultsIndividual;
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
    /**
     * @param \Alaress_Date_Format $deletedAt
     * @return self
     */
    public function setDeletedAt($deletedAt) {
        $this->deletedAt = $deletedAt;
        return $this;
    }
    /**
     * @return \Alaress_Date_Format
     */
    public function getDeletedAt() {
        return $this->deletedAt;
    }
    /**
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted() {
        return ($this->getDeletedAt() instanceof Alaress_Date_Format && !$this->getDeletedAt()->isNull());
    }
}