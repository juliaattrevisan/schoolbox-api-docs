<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment;

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
     * @var bool
     */
    private $isDynamic;


    /**
     * @var string
     */
    private $description;

    /**
     * @var null|Alaress_Date_Format
     */
    private $publishDate;

    /**
     * @var null|Alaress_Date_Format
     */
    private $openDate;

    /**
     * @var null|Alaress_Date_Format
     */
    private $dueDate;

    /**
     * @var null|Alaress_Date_Format
     */
    private $closeDate;

    /**
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
     * @return bool
     */
    public function getIsDynamic(): bool
    {
        return $this->isDynamic;
    }


    /**
     * @param bool $isDynamic
     * @return $this
     */
    public function setIsDynamic(bool $isDynamic): self
    {
        $this->isDynamic = $isDynamic;
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
     * @return Participant|GenericCollection
     */
    public function getParticipants(): GenericCollection
    {
        return $this->participants;
    }


    /**
     * @param Participant|GenericCollection $participants
     * @return $this
     */
    public function setParticipants(GenericCollection $participants): self
    {
        $this->participants->replaceWith($participants);
        return $this;
    }
}
