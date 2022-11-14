<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Season;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Alaress_Date_Format;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\ValueLabelInterface;

/**
 * Represents a Season during which sport is conducted
 *
 * @package Schoolbox\Fixtures\Season
 */
class Model implements ISoftDeletable, ValueLabelInterface
{
    // Implements ISoftDeletable
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /** @var int */
    protected $id;

    /** @var string */
    protected $name;

    /** @var Alaress_Date_Format */
    protected $start;

    /** @var Alaress_Date_Format */
    protected $end;

    /** @var Alaress_Date_Format|null */
    private ?Alaress_Date_Format $archivedAt = null;

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
        return $this->getArchivedAt() instanceof Alaress_Date_Format;
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
     * @return Alaress_Date_Format|null
     */
    public function getArchivedAt(): ?Alaress_Date_Format
    {
        return $this->archivedAt;
    }

    /**
     * @param Alaress_Date_Format|null $archivedAt
     *
     * @return static
     */
    public function setArchivedAt(?Alaress_Date_Format $archivedAt): self
    {
        $this->archivedAt = $archivedAt;
        return $this;
    }
}
