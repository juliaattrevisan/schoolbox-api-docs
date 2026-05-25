<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment;

use Schoolbox\Assessment\Participant\Plan\Mapper as PlanMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Alaress_Date_Format;

/**
 * Represents the 'domain object' version of a participation plan.
 *
 * This distinction between 'domain object' and 'data model' plan is that:
 * - a 'domain object' plan is composed of an IAssessable and collection of 'domain object' Participants.
 * - a 'data model' plan is composed of a DataModel\Assessment and collection of DataModel\Participants.
 */
class Plan
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * @var IAssessable
     */
    private $assessable;

    /**
     * @var int
     */
    private $isDynamic;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $description;

    /**
     * @var null|Alaress_Date_Format
     */
    private ?Alaress_Date_Format $publishDate = null;

    /**
     * @var null|Alaress_Date_Format
     */
    private ?Alaress_Date_Format $openDate = null;

    /**
     * @var null|Alaress_Date_Format
     */
    private ?Alaress_Date_Format $dueDate = null;

    /**
     * @var null|Alaress_Date_Format
     */
    private ?Alaress_Date_Format $closeDate = null;

    /**
     * Indexed by user.id
     *
     * @var Participant[]|GenericCollection
     */
    private $participants;


    /**
     * @param IAssessable $assessable
     */
    public function __construct(IAssessable $assessable)
    {
        $this->assessable   = $assessable;
        $this->participants = new GenericCollection(Participant::class);
    }


    // Getters/Setters


    /**
     * @return IAssessable
     */
    public function getAssessable(): IAssessable
    {
        return $this->assessable;
    }


    /**
     * @param IAssessable $assessable
     * @return $this
     */
    public function setAssessable(IAssessable $assessable): self
    {
        $this->assessable = $assessable;
        return $this;
    }

    /**
     * Check if the plan is a custom participation plan
     *
     * @return bool
     */
    public function isCustomParticipation(): bool
    {
        return PlanMapper::PLAN_TYPE_CUSTOM_PARTICIPATION === $this->isDynamic;
    }

    /**
     * @return bool
     */
    public function isDynamic(): bool
    {
        return PlanMapper::PLAN_TYPE_DYNAMIC === $this->isDynamic;
    }

    /**
     * Check if the plan is a static plan
     *
     * @return bool
     */
    public function isStatic(): bool
    {
        return PlanMapper::PLAN_TYPE_STATIC === $this->isDynamic;
    }

    /**
     * @return int
     */
    public function getIsDynamic(): int
    {
        return $this->isDynamic;
    }


    /**
     * @param int $isDynamic
     * @return $this
     */
    public function setIsDynamic(int $isDynamic): self
    {
        $this->isDynamic = $isDynamic;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }


    /**
     * @param string|null $name
     * @return $this
     */
    public function setName(?string $name)
    {
        $this->name = $name;
        return $this;
    }


    /**
     * @return null|string
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }


    /**
     * @param null|string $description
     * @return $this
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }


    /**
     * @return null|Alaress_Date_Format
     */
    public function getPublishDate(): ?Alaress_Date_Format
    {
        return $this->publishDate;
    }


    /**
     * @param null|Alaress_Date_Format $publishDate
     * @return $this
     */
    public function setPublishDate(?Alaress_Date_Format $publishDate): self
    {
        $this->publishDate = $publishDate;
        return $this;
    }


    /**
     * @return null|Alaress_Date_Format
     */
    public function getOpenDate(): ?Alaress_Date_Format
    {
        return $this->openDate;
    }


    /**
     * @param null|Alaress_Date_Format $openDate
     * @return $this
     */
    public function setOpenDate(?Alaress_Date_Format $openDate): self
    {
        $this->openDate = $openDate;
        return $this;
    }


    /**
     * @return null|Alaress_Date_Format
     */
    public function getDueDate(): ?Alaress_Date_Format
    {
        return $this->dueDate;
    }


    /**
     * @param null|Alaress_Date_Format $dueDate
     * @return $this
     */
    public function setDueDate(?Alaress_Date_Format $dueDate): self
    {
        $this->dueDate = $dueDate;
        return $this;
    }


    /**
     * @return null|Alaress_Date_Format
     */
    public function getCloseDate(): ?Alaress_Date_Format
    {
        return $this->closeDate;
    }


    /**
     * @param null|Alaress_Date_Format $closeDate
     * @return $this
     */
    public function setCloseDate(?Alaress_Date_Format $closeDate): self
    {
        $this->closeDate = $closeDate;
        return $this;
    }


    /**
     * Returns this plan's participants *INDEXED BY user.id*
     *
     * @return Participant[]|GenericCollection
     */
    public function getParticipants(): GenericCollection
    {
        return $this->participants;
    }


    /**
     * IMPORTANT: The participants must be indexed by user.id
     *
     * @param GenericCollection $participants
     * @return $this
     */
    public function setParticipants(GenericCollection $participants): self
    {
        $this->participants->replaceWith($participants);
        return $this;
    }
}
