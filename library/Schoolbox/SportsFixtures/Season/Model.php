<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Season;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use \Alaress_Date_Format;
use \Schoolbox\ValueLabelInterface;

/**
 * Represents a Season during which sport is conducted
 *
 * @package Schoolbox\SportsFixtures\Season
 */
class Model implements ISoftDeletable, ValueLabelInterface
{
    // Implements ISoftDeletable
    use DeletedAtDate;

    /** @var int */
    protected $id;

    /** @var string */
    protected $name;

    /** @var Alaress_Date_Format */
    protected $start;

    /** @var Alaress_Date_Format */
    protected $end;

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

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function findValueLabelPair()
    {
        return [
            'value' => $this->getId(),
            'label' => "{$this->getName()} - {$this->getStart()->getLocalShort()} to {$this->getEnd()->getLocalShort()}"
        ];
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
     * @param Alaress_Date_Format $start
     * @return self
     */
    public function setStart(Alaress_Date_Format $start) {
        $this->start = $start;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getStart() {
        return $this->start;
    }

    /**
     * @param Alaress_Date_Format $end
     * @return self
     */
    public function setEnd(Alaress_Date_Format $end) {
        $this->end = $end;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getEnd() {
        return $this->end;
    }

    /**
     * @param Alaress_Date_Format $createdAt
     * @return self
     */
    public function setCreatedAt(Alaress_Date_Format $createdAt) {
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
    public function setUpdatedAt(Alaress_Date_Format $updatedAt) {
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