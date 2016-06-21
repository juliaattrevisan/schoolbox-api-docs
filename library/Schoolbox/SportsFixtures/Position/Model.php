<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Position;

use Schoolbox\ISoftDeletable;
use \Alaress_Date_Format;
use \Schoolbox\SportsFixtures\Sport\Model as Sport;

class Model implements ISoftDeletable
{
    /** @var int */
    protected $id;

    /** @var Sport */
    protected $sport;

    /** @var string */
    protected $name;

    /** @var Alaress_Date_Format */
    protected $createdAt;

    /** @var Alaress_Date_Format */
    protected $updatedAt;

    /** @var Alaress_Date_Format */
    protected $deletedAt;

    /**
     * Model constructor.
     */
    public function __construct()
    {
    }

    /**
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted() {
        return ($this->getDeletedAt() instanceof Alaress_Date_Format && !$this->getDeletedAt()->isNull());
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
     * @param Sport $sport
     * @return self
     */
    public function setSport($sport) {
        $this->sport = $sport;
        return $this;
    }

    /**
     * @return Sport
     */
    public function getSport() {
        return $this->sport;
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
     * @param Alaress_Date_Format $deletedAt
     * @return self
     */
    public function setDeletedAt($deletedAt) {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    /**
     * @return Alaress_Date_Format $deletedAt
     */
    public function getDeletedAt() {
        return $this->deletedAt;
    }
}