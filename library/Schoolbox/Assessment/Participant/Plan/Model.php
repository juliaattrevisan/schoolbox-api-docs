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
 *
 *
 * Steve Notes
 * -----------
 * There's been a long-running mystery about what a "Dynamic Plan" means vs a "Static Plan". There have been no formal
 * definitions nor a traceable history of its meaning, but after __six gruelling years__ I've finally figured it out.
 * Elucidation follows.
 *
 *
 * Every time a learning activity IAssessable is created, a Plan is spawned for it.
 * Every learning activity will always have at least one Plan for it.
 *
 * By default, this will be a "Dynamic Plan"; this means two things will be presumed by the system.
 *
 *     - the enrolled students (as defined by the sis) will use this "default plan" to inform them of their title,
 *       description and dates. Initially, everyone will use the title/description/dates as defined by the learning
 *       activity and will change whenever the settings of the learning activity changes (and/or when the students
 *       change in the sis). This is why it's called a "Dynamic Plan" - it defers (and refers) to whatever the default
 *       behaviour would be without any person-specific customisations and will update with the assessment's settings
 *       whenever (if ever) they were to change.
 *
 *     - a "static plan" is a customised version of the Dynamic Plan and overrides (replaces) whatever the learning
 *       activity's settings are. This allows everyone else on the assessment's Dynamic Plan to be managed together
 *       while letting the special students have their own special requirements via their customised version of the
 *       Dynamic Plan.
 *
 * Therefore, whenever you see the idea of a "Dynamic Plan" being thrown around, you can know that it means "do whatever
 * the assessment's default behaviour is w.r.t. title/description/dates" and that a "Static Plan" means that it's been
 * overridden for a particular person(s).
 *
 *                         THIS IS WHY WE START WITH A GLOSSARY, GUYS.
 *
 *
 * A better naming would have been ->isCustomised() so you'd know whether a Plan instance was going to do something
 * other than the default, rather than have two undefined words "Dynamic" and "Static"..............
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
     * Check if the plan is a custom participation plan
     *
     * @return bool
     */
    public function isCustomParticipation(): bool
    {
        return Mapper::PLAN_TYPE_CUSTOM_PARTICIPATION === $this->isDynamic;
    }

    /**
     * @return bool
     */
    public function isDynamic(): bool
    {
        return Mapper::PLAN_TYPE_DYNAMIC === $this->isDynamic;
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

    /**
     * Get the customisation type of the plan
     * if not a customised plan, return null
     *
     * @return ?string
     */
    public function getCustomisationType(): ?string
    {
        if ($this->isCustomParticipation()) {
            return Mapper::CUSTOMISATION_TYPE_CUSTOM_PARTICIPATION;
        }

        if ($this->getIsDynamic() === Mapper::PLAN_TYPE_STATIC) {
            return Mapper::CUSTOMISATION_TYPE_STATIC;
        }

        return null;
    }
}
