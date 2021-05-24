<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Season;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Alaress_Date_Format;
use Schoolbox\ValueLabelInterface;

/**
 * Represents a Season during which sport is conducted
 *
 * @package Schoolbox\Fixtures\Season
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
    private $archivedAt;

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


    /**
     * Return whether this season has been archived
     *
     * @return bool
     */
    public function isArchived()
    {
        return !$this->getArchivedAt()->isNull();
    }

    /* Getters/Setters
     */

    /**
     * @param int $id
     * @return static
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
     * @return static
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
     * @param Alaress_Date_Format $start
     * @return static
     */
    public function setStart(Alaress_Date_Format $start)
    {
        $this->start = $start;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * @param Alaress_Date_Format $end
     * @return static
     */
    public function setEnd(Alaress_Date_Format $end)
    {
        $this->end = $end;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getEnd()
    {
        return $this->end;
    }

    /**
     * @param Alaress_Date_Format $archivedAt
     * @return static
     */
    public function setArchivedAt(Alaress_Date_Format $archivedAt)
    {
        $this->archivedAt = $archivedAt;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getArchivedAt()
    {
        return $this->archivedAt;
    }

    /**
     * @param Alaress_Date_Format $createdAt
     * @return static
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
     * @return static
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
}
