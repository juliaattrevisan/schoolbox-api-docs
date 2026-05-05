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
}
