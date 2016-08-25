<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\School;


use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use \Alaress_Date_Format;

class Model implements ISoftDeletable
{
    // Implements ISoftDeletable
    use DeletedAtDate;

    /** @var int */
    protected $id;

    /** @var string */
    protected $name;

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
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @param int $id
     * @return static
     */
    public function setId($id) {
        $this->id = $id;
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
     * @return Alaress_Date_Format
     */
    public function getCreatedAt() {
        return $this->createdAt;
    }


    /**
     * @param Alaress_Date_Format $createdAt
     * @return static
     */
    public function setCreatedAt($createdAt) {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getUpdatedAt() {
        return $this->updatedAt;
    }

    /**
     * @param Alaress_Date_Format $updatedAt
     * @return static
     */
    public function setUpdatedAt($updatedAt) {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}