<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Position;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Fixtures\Sport\Model as Sport;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

/**
 * Represents a Position a player may take in some Sport.
 * Eg, 'Full Forward' or 'Full Back' in Aussie Rules Football
 *
 * For the time being, a participant may only be assigned 0 or 1 position
 *
 * @package Schoolbox\Fixtures\Position
 */
class Model implements ISoftDeletable, \JsonSerializable
{
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /** @var int */
    protected $id;

    /** @var Sport */
    protected $sport;

    /** @var string */
    protected $name;

    /**
     * The external identifier in the third party system
     * e.g. a Clipboard identifier, so we know where our
     * data came from
     *
     * @var string|null
     */
    private ?string $externalId = null;

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
     * @param string $name
     * @return self
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
     * @param Sport $sport
     * @return self
     */
    public function setSport($sport)
    {
        $this->sport = $sport;
        return $this;
    }

    /**
     * @return Sport
     */
    public function getSport()
    {
        return $this->sport;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'id'   => $this->getId(),
            'name' => $this->getName(),
        ];
    }


    /**
     * @return string|null
     */
    public function getExternalId(): ?string
    {
        return $this->externalId;
    }


    /**
     * @param string|null $externalId
     * @return $this
     */
    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;
        return $this;
    }
}
