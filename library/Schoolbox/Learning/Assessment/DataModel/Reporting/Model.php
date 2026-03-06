<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\Reporting;

use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\GenericCollection;
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
     * This is used to decide whether to show any supporting data at all.
     *
     * @var bool|null
     */
    private ?bool $supportingDataEnable = null;


    /**
     * An int corresponding to one of the date range options in the ui
     *
     * @var string|null
     */
    private ?string $supportingDataRange = null;


    /**
     * @var GenericCollection|null
     */
    private ?GenericCollection $supportingDataCurriculumNodes = null;


    /**
     * These are used to constrain the folders in which we'll look for assessments for the mega markbook. To minimise
     * hydration users should select the most appropriate time period for which we fetch assessments.
     *
     * We look in this time period for assessments due within that time period;
     * also, we find any published feedback within that time period to minimise hydration to determine a person's
     * Feedback footprint.
     *
     * @var string|null
     */
    private ?string $supportingDataFolders = null;


    /**
     * These are used to constrain the assessments being fetched for the mega markbook. To minimise hydration people
     * should specify all the work types that would be needed to perform the reporting activity's feedback.
     *
     * As per tradition, zero work types selected == all work types selected
     *
     * @var GenericCollection|null
     */
    private ?GenericCollection $supportingDataWorkTypes = null;


    /**
     * This decides whether the Reporting activity's feedback screen shows an aggregated markbook from students'
     * homepages. This is optional for reporting activities that are for attendance and don't require academic details
     *
     * @var bool|null
     */
    private ?bool $supportingDataMarkbookEnable = null;


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


    /**
     * @return bool|null
     */
    public function getSupportingDataEnable(): ?bool
    {
        return $this->supportingDataEnable;
    }


    /**
     * @param bool|null $supportingDataEnable
     * @return $this
     */
    public function setSupportingDataEnable(?bool $supportingDataEnable): self
    {
        $this->supportingDataEnable = $supportingDataEnable;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getSupportingDataRange(): ?string
    {
        return $this->supportingDataRange;
    }


    /**
     * @param string|null $supportingDataRange
     * @return $this
     */
    public function setSupportingDataRange(?string $supportingDataRange): self
    {
        $this->supportingDataRange = $supportingDataRange;
        return $this;
    }


    /**
     * @return GenericCollection|null
     */
    public function getSupportingDataCurriculumNodes(): ?GenericCollection
    {
        return $this->supportingDataCurriculumNodes;
    }


    /**
     * @param GenericCollection|null $supportingDataCurriculumNodes
     * @return $this
     */
    public function setSupportingDataCurriculumNodes(?GenericCollection $supportingDataCurriculumNodes): self
    {
        $this->supportingDataCurriculumNodes = $supportingDataCurriculumNodes;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getSupportingDataFolders(): ?string
    {
        return $this->supportingDataFolders;
    }


    /**
     * @param string|null $supportingDataFolders
     * @return $this
     */
    public function setSupportingDataFolders(?string $supportingDataFolders): self
    {
        $this->supportingDataFolders = $supportingDataFolders;
        return $this;
    }


    /**
     * @return GenericCollection|null
     */
    public function getSupportingDataWorkTypes(): ?GenericCollection
    {
        return $this->supportingDataWorkTypes;
    }


    /**
     * @param GenericCollection|null $supportingDataWorkTypes
     * @return $this
     */
    public function setSupportingDataWorkTypes(?GenericCollection $supportingDataWorkTypes): self
    {
        $this->supportingDataWorkTypes = $supportingDataWorkTypes;
        return $this;
    }


    /**
     * @return bool|null
     */
    public function getSupportingDataMarkbookEnable(): ?bool
    {
        return $this->supportingDataMarkbookEnable;
    }


    /**
     * @param bool|null $supportingDataMarkbookEnable
     * @return $this
     */
    public function setSupportingDataMarkbookEnable(?bool $supportingDataMarkbookEnable): self
    {
        $this->supportingDataMarkbookEnable = $supportingDataMarkbookEnable;
        return $this;
    }
}
