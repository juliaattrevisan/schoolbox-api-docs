<?php

/**
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Participant\Plan;

use Schoolbox\Assessment\Participant;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Alaress_Date_Format;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\Learning\Assessment\DataModel as Assessment;

/**
 * Represents the 'data model' version of a participation plan.
 *
 * This distinction between 'domain object' and 'data model' plan is that:
 * - a 'domain object' plan is composed of an IAssessable and collection of 'domain object' Participants.
 * - a 'data model' plan is composed of a DataModel\Assessment and collection of DataModel\Participants.
 */
class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;
    use ModelTrait\CreateCollection;

    /**
     * @var Assessment\Model
     *
     */
    private $assessment;

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
     * @var Participant\Model[]|GenericCollection
     */
    private $participants;


    // Getters/Setters


    /**
     * @return Assessment\Model
     */
    public function getAssessment(): Assessment\Model
    {
        return $this->assessment;
    }


    /**
     * @param Assessment\Model $assessment
     * @return $this
     */
    public function setAssessment(Assessment\Model $assessment): self
    {
        $this->assessment = $assessment;
        return $this;
    }


    /**
     * @return int
     */
    public function isDynamic(): int
    {
        return $this->isDynamic;
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
     * @return Alaress_Date_Format|null
     */
    public function getPublishDate(): ?Alaress_Date_Format
    {
        return $this->publishDate;
    }


    /**
     * @param Alaress_Date_Format|null $publishDate
     *
     * @return static
     */
    public function setPublishDate(?Alaress_Date_Format $publishDate): self
    {
        $this->publishDate = $publishDate;
        return $this;
    }


    /**
     * @return Alaress_Date_Format|null
     */
    public function getOpenDate(): ?Alaress_Date_Format
    {
        return $this->openDate;
    }


    /**
     * @param Alaress_Date_Format|null $openDate
     *
     * @return static
     */
    public function setOpenDate(?Alaress_Date_Format $openDate): self
    {
        $this->openDate = $openDate;
        return $this;
    }


    /**
     * @return Alaress_Date_Format|null
     */
    public function getDueDate(): ?Alaress_Date_Format
    {
        return $this->dueDate;
    }


    /**
     * @param Alaress_Date_Format|null $dueDate
     *
     * @return static
     */
    public function setDueDate(?Alaress_Date_Format $dueDate): self
    {
        $this->dueDate = $dueDate;
        return $this;
    }


    /**
     * @return Alaress_Date_Format|null
     */
    public function getCloseDate(): ?Alaress_Date_Format
    {
        return $this->closeDate;
    }


    /**
     * @param Alaress_Date_Format|null $closeDate
     *
     * @return static
     */
    public function setCloseDate(?Alaress_Date_Format $closeDate): self
    {
        $this->closeDate = $closeDate;
        return $this;
    }


    /**
     * @return Participant\Model[]|GenericCollection
     */
    public function getParticipants(): GenericCollection
    {
        return $this->participants;
    }


    /**
     * @param Participant\Model[]|GenericCollection $participants
     * @return $this
     */
    public function setParticipants(GenericCollection $participants): self
    {
        $participants->assertCollectionOf(Participant\Model::class);
        $this->participants = $participants;
        return $this;
    }
}
