<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Reporting;

use InvalidArgumentException;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\AbstractAssessment;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\User;

class Model extends AbstractAssessment
{
    /**
     * This appears on Create Activity screens but you probably need to rename
     * this to something friendlier because it's user-facing.
     */
    private const string DEFAULT_TYPE_NAME = 'Reporting';


    /**
     * The IAssessable associated with this activity
     */
    private ?AssessmentModel $assessmentModel = null;


    /**
     * @var float|null
     */
    private ?float $instructorMaxMark = null;


    /**
     * @var float|null
     */
    private ?float $maxMark = null;


    /**
     * @var MarkType|null
     */
    private ?MarkType $instructorMarkType = null;


    /**
     * @var MarkType|null
     */
    private ?MarkType $markType = null;


    /**
     * @var GradeGroup|null
     */
    private ?GradeGroup $instructorGradeGroup = null;


    /**
     * @var GradeGroup|null
     */
    private ?GradeGroup $gradeGroup = null;


    /**
     * This is used to convert normalised marks to human readable, and vice versa, according to the Mark Type chosen
     * Used for the Instructor Marks that staff see.
     *
     * (AbstractAssessment already sets a markScheme instance which is used for students' display marks.)
     *
     * @var IMarkScheme|null
     */
    private ?IMarkScheme $instructorMarkScheme = null;


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
     * A string corresponding to one of the date range options in the ui
     *
     * @var string|null
     */
    private ?string $supportingDataRange = null;


    /**
     * These are used to constrain the assessments being fetched for the mega markbook. To minimise hydration people
     * should specify all the nodes that would be needed to perform the reporting activity's feedback.
     *
     * As per tradition, zero curriculum nodes selected == all curriculum nodes selected
     *
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
     * The user who created this activity
     *
     * @var User|null
     */
    private ?User $createdBy = null;


    /**
     * The user who most recently updated this activity
     *
     * @var User|null
     */
    private ?User $updatedBy = null;


    /**
     * The user who deleted this activity
     *
     * @var User|null
     */
    private ?User $deletedBy = null;


    /**
     * @return string
     */
    public static function getDefaultTypeName(): string
    {
        return self::DEFAULT_TYPE_NAME;
    }


    /**
     * Decide if $learner can create a response to this activity.
     *
     * @param User $learner
     * @return bool
     */
    public function canLearnerCreateResponse(User $learner): bool
    {
        return false;
    }


    /**
     * This model's "kind of assessment" used to identify it
     * among the family of AbstractAssessment subclasses
     *
     * @return string
     */
    public static function getFamilyName(): string
    {
        return AssessmentType::TYPE_REPORTING;
    }


    /**
     * @inheritdoc
     */
    public function canInstructorCreateResponse(User $instructor, User $submittedOnBehalfOf)
    {
        // Project containers cannot have IResponses directly on them. Only their sub-assessments can.
        return false;
    }


    /**
     * @param Participant $participant
     * @param $publishedOnly
     * @return float|null
     */
    public function getNormalisedMark(Participant $participant, $publishedOnly): ?float
    {
        /* TODO Implement this
         */
        return null;
    }


    /**
     * @param Participant $participant
     * @param $publishedOnly
     * @return IResponse|null
     */
    public function getAuthoritativeResponse(Participant $participant, $publishedOnly): ?IResponse
    {
        /* TODO Implement this
         */
        return null;
    }


    /**
     * @param Participant $participant
     * @param $publishedOnly
     * @return IFeedback|null
     */
    public function getAuthoritativeFeedback(Participant $participant, $publishedOnly): ?IFeedback
    {
        $this->assertSameAssessment($participant);

        /* TODO Implement this
         */
        return null;
    }


    /**
     *
     * @param Participant $participant
     * @return bool
     */
    public function isFeedbackRequired(Participant $participant): bool
    {
        $this->assertSameAssessment($participant);

        /* TODO Implement this
         */
        return false;
    }


    /**
     * Decide if this participant has a published feedback. That's a bit of a weird hair-splitting given that
     * isFeedbackRequired() also exists, but ok
     *
     * @param Participant $participant
     * @return bool
     */
    public function hasPublishedFeedback(Participant $participant)
    {
        $this->assertSameAssessment($participant);

        /* TODO Implement this
         */
        return false;
    }


    /**
     * @param Participant $participant
     * @return bool
     */
    public function hasPendingDraftAuthoritativeFeedback(Participant $participant): bool
    {
        /* TODO implement this
         */
        return false;
    }


    /* Assertions
     * For some reason these throw exceptions when their requirements are not met, even though these aren't
     * error states, egregious misconfigurations or particularly extraordinary setups. I think these are
     * totally expected circumstances that we need to handle with a bit more delicacy.
     */

    /**
     * Check the dates set on this model are valid
     *
     * @throws InvalidArgumentException if the dates are not validly set
     * @return void
     */
    public function assertValidDates(): void
    {
        /* Check publish date against everything else
         * Don't check dates if in course
         */
        if ($this->getBaseContext() instanceof UnitSpecification) {
            return;
        }

        $this->assertDateSequence([
            'publish' => $this->getPublishAt(),
            'open' => $this->getOpenAt(),
            'due' => $this->getDueAt(),
            'late' => $this->getCloseAt()
        ]);
    }


    /**
     * Assert whether a student can create a response to this learning activity
     *
     * @throws \DomainException
     */
    public function assertCanRespond(?Participant $participant = null): void
    {
        /* This is perpetually closed to students
         */
        throw new \DomainException('', ErrorMapper::ERROR_ASSESSMENT_CLOSED);
    }

    /**
     * Used for Course Export.
     *
     * Copy implementation back to assessment along with any activity-specific fields beyond the ones
     * already pre-defined in AbstractAssessment's overwrite()
     */
    public function overwrite(IAssessable $source): IAssessable
    {
        parent::overwrite($source);

        return $this;
    }


    /**
     * @inheritDoc
     */
    public function isCustomParticipationCopyable(): bool
    {
        /* At the moment, we want to restrict the copying of custom participation
         * for reporting, which will be handled in the future, so for the time being, we will leave it out.
         */
        return false;
    }


    /* Getters/Setters
     */

    /**
     * @return AssessmentModel|null
     */
    public function getAssessmentModel(): ?AssessmentModel
    {
        return $this->assessmentModel;
    }


    /**
     * @param AssessmentModel|null $assessmentModel
     * @return void
     */
    public function setAssessmentModel(?AssessmentModel $assessmentModel): void
    {
        $this->assessmentModel = $assessmentModel;
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
     * @return static
     */
    public function setMaxMark(?float $maxMark): self
    {
        $this->maxMark = $maxMark;
        return $this;
    }


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
     * @return GradeGroup|null
     */
    public function getInstructorGradeGroup(): ?GradeGroup
    {
        return $this->instructorGradeGroup;
    }


    /**
     * @param GradeGroup|null $instructorGradeGroup
     * @return $this
     */
    public function setInstructorGradeGroup(?GradeGroup $instructorGradeGroup): self
    {
        $this->instructorGradeGroup = $instructorGradeGroup;
        return $this;
    }


    /**
     * @return GradeGroup|null
     */
    public function getGradeGroup(): ?GradeGroup
    {
        return $this->gradeGroup;
    }


    /**
     * @param GradeGroup|null $gradeGroup
     * @return $this
     */
    public function setGradeGroup(?GradeGroup $gradeGroup): self
    {
        $this->gradeGroup = $gradeGroup;
        return $this;
    }


    /**
     * @return IMarkScheme|null
     */
    public function getInstructorMarkScheme(): ?IMarkScheme
    {
        return $this->instructorMarkScheme;
    }


    /**
     * @param IMarkScheme|null $instructorMarkScheme
     * @return $this
     */
    public function setInstructorMarkScheme(?IMarkScheme $instructorMarkScheme): self
    {
        $this->instructorMarkScheme = $instructorMarkScheme;
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


    /* Auditor fields
     */

    /**
     * @return User|null
     */
    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }


    /**
     * @param User|null $createdBy
     * @return $this
     */
    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }


    /**
     * @return User|null
     */
    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }


    /**
     * @param User|null $updatedBy
     * @return $this
     */
    public function setUpdatedBy(?User $updatedBy): self
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }


    /**
     * @return User|null
     */
    public function getDeletedBy(): ?User
    {
        return $this->deletedBy;
    }


    /**
     * @param User|null $deletedBy
     * @return $this
     */
    public function setDeletedBy(?User $deletedBy): self
    {
        $this->deletedBy = $deletedBy;
        return $this;
    }
}
