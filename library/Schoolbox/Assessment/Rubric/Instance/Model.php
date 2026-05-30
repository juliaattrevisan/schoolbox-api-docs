<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Instance;

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Rubric\Grid\Grid;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Assessment\DataModel\AssessmentModelTrait;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Mapper\Traits\Model as ModelTrait;

class Model implements IAssessableDataModel, ISoftDeletable
{
    use ModelTrait\Id;
    use AssessmentModelTrait;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;


    /**
     * An optional name for the rubric grid; existing grids pre-26.1 won't have a name.
     *
     * @var string|null
     */
    private ?string $title = null;


    /**
     * All the grids in an assessment get aggregated similar to Projects, like a weighted average
     *
     * @var float|null
     */
    private ?float $weight = null;


    /**
     * Each capability optionally has a mark type
     *
     * @var SubmissionBox\MarkType|null
     */
    private ?SubmissionBox\MarkType $capabilityMarkType = null;


    /**
     * If Letter Grade is chosen you can have a set of grade level(s) associated with that mark type
     *
     * @var SubmissionBox\GradeLevel\Group\Model|null
     */
    private ?SubmissionBox\GradeLevel\Group\Model $capabilityGradeGroup = null;


    /**
     * Each grid has an option for "Use Mark Range" which was meant to let descriptors have a potential range of values
     * from which to choose. I haven't been able to figure it out through the ui though, which is a bit disconcerting
     *
     * @var bool|null
     */
    private ?bool $markedUsingRange = null;


    /**
     * A generic place for a manufactured Grid instance, for whatever contextual purpose you needed.
     * This was primarily to service the learning activities' Decorators which assembled rubric grids, but then needed
     * it to exist in the $assessment instance for some other method to process once it was spawned by the Factory.
     *
     * @var Grid|null
     */
    private ?Grid $grid = null;


    /**
     * Whether this rubric instance has a meaningful mark per capability setting activated
     * If null, or None, then mark per capability is presumed ot be off.
     *
     * @return bool
     */
    public function isMarkPerCapability(): bool
    {
        return $this->capabilityMarkType !== null && $this->capabilityMarkType->getId() !== MarkType::TYPE_NONE;
    }


    /* Getters/Setters
     */

    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }


    /**
     * @param string|null $title
     * @return $this
     */
    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }


    /**
     * @return float|null
     */
    public function getWeight(): ?float
    {
        return $this->weight;
    }


    /**
     * @param float|null $weight
     * @return $this
     */
    public function setWeight(?float $weight): self
    {
        $this->weight = $weight;
        return $this;
    }


    /**
     * @return SubmissionBox\MarkType|null
     */
    public function getCapabilityMarkType(): ?SubmissionBox\MarkType
    {
        return $this->capabilityMarkType;
    }


    /**
     * @param SubmissionBox\MarkType|null $capabilityMarkType
     * @return $this
     */
    public function setCapabilityMarkType(?SubmissionBox\MarkType $capabilityMarkType): self
    {
        $this->capabilityMarkType = $capabilityMarkType;
        return $this;
    }


    /**
     * @return SubmissionBox\GradeLevel\Group\Model|null
     */
    public function getCapabilityGradeGroup(): ?SubmissionBox\GradeLevel\Group\Model
    {
        return $this->capabilityGradeGroup;
    }


    /**
     * @param SubmissionBox\GradeLevel\Group\Model|null $capabilityGradeGroup
     * @return $this
     */
    public function setCapabilityGradeGroup(?SubmissionBox\GradeLevel\Group\Model $capabilityGradeGroup): self
    {
        $this->capabilityGradeGroup = $capabilityGradeGroup;
        return $this;
    }


    /**
     * @return bool|null
     */
    public function getMarkedUsingRange(): ?bool
    {
        return $this->markedUsingRange;
    }


    /**
     * @param bool|null $markedUsingRange
     * @return $this
     */
    public function setMarkedUsingRange(?bool $markedUsingRange): self
    {
        $this->markedUsingRange = $markedUsingRange;
        return $this;
    }


    /**
     * @return Grid|null
     */
    public function getGrid(): ?Grid
    {
        return $this->grid;
    }


    /**
     * @param Grid|null $grid
     * @return $this
     */
    public function setGrid(?Grid $grid): self
    {
        $this->grid = $grid;
        return $this;
    }
}
