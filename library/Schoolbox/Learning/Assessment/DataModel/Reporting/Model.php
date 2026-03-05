<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\Reporting;

use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\ISoftDeletable;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel;
use Schoolbox\Learning\Assessment\DataModel\AssessmentModelTrait;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\CreatedBy;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedBy;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedBy;

class Model implements IAssessableDataModel, ISoftDeletable
{
    use Id;
    use AssessmentModelTrait;
    use CreatedAtDate;
    use CreatedBy;
    use UpdatedAtDate;
    use UpdatedBy;
    use DeletedAtDate;
    use DeletedBy;

    /**
     * This is the instructor mark type which is only visible to staff
     *
     * @var null|MarkType
     */
    private ?MarkType $instructorMarkType = null;


    /**
     * This is the display mark type which is seen by students
     *
     * @var null|MarkType
     */
    private ?MarkType $markType = null;


    /**
     * This is the grade level associated with the Letter Grade mark type that is seen by staff
     * (optional: only applies if Letter Grade was chosen)
     *
     * @var null|GradeLevel\Group\Model
     */
    private ?GradeLevel\Group\Model $instructorGradeGroup = null;


    /**
     * This is the grade level associated with the Letter Grade mark type that is seen by students
     * (optional: only applies if Letter Grade was chosen)
     *
     * @var null|GradeLevel\Group\Model
     */
    private ?GradeLevel\Group\Model $gradeGroup = null;


    /**
     * This is the max mark available for the Total Mark scheme, seen by staff
     *
     * @var null|float
     */
    private ?float $instructorMaxMark = null;


    /**
     * This is the max mark available for the Total Mark scheme, seen by students
     *
     * @var null|float
     */
    private ?float $maxMark = null;


    /**
     * Decides which demographic can see the marks associated with this activity.
     * These correspond to consts in IAssessable
     *
     * @var null|int
     */
    private ?int $markVisibility = null;


    /**
     * This is used to decide if the feedbacks should have a ckeditor for people to write comments. This is optional for
     * reporting activities that have no value offering comments, such as attendance or other clerical data.
     *
     * @var bool|null
     */
    private ?bool $feedbackCommentsEnable = null;


    /**
     * @return MarkType|null
     */
    public function getInstructorMarkType(): ?MarkType
    {
        return $this->instructorMarkType;
    }


    /**
     * @param MarkType|null $instructorMarkType
     * @return $this
     */
    public function setInstructorMarkType(?MarkType $instructorMarkType): self
    {
        $this->instructorMarkType = $instructorMarkType;
        return $this;
    }


    /**
     * @return MarkType|null
     */
    public function getMarkType(): ?MarkType
    {
        return $this->markType;
    }


    /**
     * @param MarkType|null $markType
     * @return $this
     */
    public function setMarkType(?MarkType $markType): self
    {
        $this->markType = $markType;
        return $this;
    }


    /**
     * @return GradeLevel\Group\Model|null
     */
    public function getInstructorGradeGroup(): ?GradeLevel\Group\Model
    {
        return $this->instructorGradeGroup;
    }


    /**
     * @param GradeLevel\Group\Model|null $instructorGradeGroup
     * @return $this
     */
    public function setInstructorGradeGroup(?GradeLevel\Group\Model $instructorGradeGroup): self
    {
        $this->instructorGradeGroup = $instructorGradeGroup;
        return $this;
    }


    /**
     * @return GradeLevel\Group\Model|null
     */
    public function getGradeGroup(): ?GradeLevel\Group\Model
    {
        return $this->gradeGroup;
    }


    /**
     * @param GradeLevel\Group\Model|null $gradeGroup
     * @return $this
     */
    public function setGradeGroup(?GradeLevel\Group\Model $gradeGroup): self
    {
        $this->gradeGroup = $gradeGroup;
        return $this;
    }


    /**
     * @return float|null
     */
    public function getInstructorMaxMark(): ?float
    {
        return $this->instructorMaxMark;
    }


    /**
     * @param float|null $instructorMaxMark
     * @return $this
     */
    public function setInstructorMaxMark(?float $instructorMaxMark): self
    {
        $this->instructorMaxMark = $instructorMaxMark;
        return $this;
    }


    /**
     * @return float|null
     */
    public function getMaxMark(): ?float
    {
        return $this->maxMark;
    }


    /**
     * @param float|null $maxMark
     * @return $this
     */
    public function setMaxMark(?float $maxMark): self
    {
        $this->maxMark = $maxMark;
        return $this;
    }


    /**
     * @return int|null
     */
    public function getMarkVisibility(): ?int
    {
        return $this->markVisibility;
    }


    /**
     * @param int|null $markVisibility
     * @return $this
     */
    public function setMarkVisibility(?int $markVisibility): self
    {
        $this->markVisibility = $markVisibility;
        return $this;
    }


    /**
     * @return bool|null
     */
    public function getFeedbackCommentsEnable(): ?bool
    {
        return $this->feedbackCommentsEnable;
    }


    /**
     * @param bool|null $feedbackCommentsEnable
     * @return $this
     */
    public function setFeedbackCommentsEnable(?bool $feedbackCommentsEnable): self
    {
        $this->feedbackCommentsEnable = $feedbackCommentsEnable;
        return $this;
    }

}
