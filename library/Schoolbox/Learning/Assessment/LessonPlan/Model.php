<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\LessonPlan;

use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\AbstractAssessment;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Field\Model as LessonPlanFieldModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\User;

class Model extends AbstractAssessment
{
    /**
     * @var int|null
     */
    private ?int $lessonPlanTemplate = null;


    /**
     * The IAssessable associated with this Lesson Plan
     */
    private ?AssessmentModel $assessmentModel = null;


    /**
     * The user who created this lesson plan template
     *
     * @var User|null
     */
    private ?User $createdBy = null;


    /**
     * The user who most recently updated this lesson plan template
     *
     * @var User|null
     */
    private ?User $updatedBy = null;


    /**
     * The user who deleted this lesson plan template
     *
     * @var User|null
     */
    private ?User $deletedBy = null;


    /**
     * A set of related Field\Model instances
     *
     * @var GenericCollection|null
     */
    private ?GenericCollection $fields = null;


    /**
     * Optionally attached Files
     *
     * @var Collection|null
     */
    protected ?Collection $attachments = null;


    /**
     * Visibility to students or staff.
     * Corresponds to Role::STUDENT or Role::STAFF
     *
     * Teachers might make Lesson Plans that are only available to teachers,
     * not students; like a reference material for what the student was meant
     * to accomplish. Students have no such use of this kind of Lesson Plan
     * and shouldn't see it.
     *
     * We've used Publish Date to decide visibility to students and control
     * visibility in other parts of schoolbox but the real reason this exists
     * is to prevent students from seeing a teacher-only Lesson Plan.
     *
     * @var string|null
     */
    private ?string $visibility = null;

    /**
     * Assessments that are linked to this lesson plan
     *
     * @var GenericCollection<IAssessable>|null
     */
    private ?GenericCollection $linkedAssessments = null;


    /**
     * @var string|null
     */
    private ?string $duration = null;


    /**
     * Creates a clone of this assessment.
     *
     * This is used when you create a new assessment in a course and you want to
     * Export To Classes
     *
     * This clone must be able to be persisted to create a copy of this assessment,
     * without further modification.
     *
     * @return static
     * @throws \UnexpectedValueException
     */
    public function cloneForCopy(): self
    {
        $clone = parent::cloneForCopy();

        /* Copy Lesson Plan Fields completely and save them
         * as entirely new Lesson Plan Fields in the db
         */
        $clone->copyFields($this);

        // copy attachments
        $clone->copyAttachedFiles($this);

        return $clone;
    }


    /**
     * Overwrite fields from `$source` into this object.
     *
     * This is used when you edit an assessment in a course that was already imported
     * and you want to Save To Course
     *
     * This implementation overwrites the additional fields over and above the
     * base implementation:
     *    * attached files
     *    * field bodies
     *    * visibility to students
     *
     * This implementation does _not_ overwrite:
     * * external ID: this uniquely identifies the base assessment within
     *   another system, so it should not be copied
     *

     *
     * @param self $source
     * @return $this
     * @throws \UnexpectedValueException
     */
    public function overwrite(IAssessable $source): IAssessable
    {
        parent::overwrite($source);
        $this->visibility = $source->visibility;

        /* Copy Lesson Plan Fields completely and save them
         * as entirely new Lesson Plan Fields in the db
         */
        $this->copyFields($source);

        // Copy attachments
        $this->copyAttachedFiles($source);

        return $this;
    }


    /**
     * Copy Lesson Plan Fields from $source into $this,
     * where $this is actually the clone.
     *
     * The clone copies from the $source into itself.
     *
     * @param Model $source
     * @return $this
     * @throws \UnexpectedValueException
     */
    private function copyFields(self $source): self
    {
        /* Clone out the Collection datatype from the source
         */
        $this->setFields($source->getFields()->emptyClone());

        /* Clone every field found in the source,
         * clear its id so it saves a new record
         */
        foreach ($source->getFields() as $field) {
            $newField = clone $field;
            $newField->setId(null);

            $this->addField($newField);
        }

        return $this;
    }


    /**
     * Copy attached files from `$source` into this object.
     *
     * @param self $source
     * @return $this
     */
    private function copyAttachedFiles(self $source): self
    {
        $newFiles = new Collection();

        foreach ($source->getAttachments() as $file) {
            $newFiles->add(
                (new File())
                    ->setStorage($file->getStorage())
                    ->setBasename($file->getBasename())
            );
        }

        $this->setAttachments($newFiles);

        return $this;
    }


    /**
     * @return int|null
     */
    public function getLessonPlanTemplate(): ?int
    {
        return $this->lessonPlanTemplate;
    }


    /**
     * @param int|null $lessonPlanTemplate
     * @return Model
     */
    public function setLessonPlanTemplate(?int $lessonPlanTemplate): self
    {
        $this->lessonPlanTemplate = $lessonPlanTemplate;
        return $this;
    }


    /**
     * @return AssessmentModel|null
     */
    public function getAssessmentModel(): ?AssessmentModel
    {
        return $this->assessmentModel;
    }


    /**
     * @param AssessmentModel|null $assessmentModel
     * @return static
     */
    public function setAssessmentModel(?AssessmentModel $assessmentModel = null): self
    {
        $this->assessmentModel = $assessmentModel;
        return $this;
    }


    /**
     * @return User|null
     */
    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    /**
     * Temporary fix for problem identified in SA-16504.
     * IAssessable has getCreator(), and this class implements IAssessable.
     * @return User|null
     */
    public function getCreator(): ?User
    {
        return $this->getCreatedBy();
    }

    /**
     * @param User|null $createdBy
     * @return Model
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
     * @return Model
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
     * @return Model
     */
    public function setDeletedBy(?User $deletedBy): self
    {
        $this->deletedBy = $deletedBy;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getVisibility(): ?string
    {
        return $this->visibility;
    }


    /**
     * @param string|null $visibility
     * @return Model
     */
    public function setVisibility(?string $visibility): self
    {
        $this->visibility = $visibility;
        return $this;
    }


    /**
     * @return GenericCollection|null
     */
    public function getFields(): ?GenericCollection
    {
        return $this->fields;
    }


    /**
     * @param GenericCollection|null $fields
     * @return Model
     */
    public function setFields(?GenericCollection $fields): self
    {
        $this->fields = $fields;
        return $this;
    }


    /**
     * Add a template field to this template
     *
     * @param LessonPlanFieldModel $field
     * @return $this
     * @throws \UnexpectedValueException
     */
    public function addField(LessonPlanFieldModel $field): self
    {
        $this->fields->add($field);
        return $this;
    }


    /**
     * Lesson Plans never have responses.
     *
     * @param User $learner
     * @return bool
     */
    public function canLearnerCreateResponse(User $learner): bool
    {
        return false;
    }


    /**
     * @return string
     */
    public static function getDefaultTypeName(): string
    {
        return 'Lesson Plan';
    }


    /**
     * This model's "kind of assessment" used to identify it
     * among the family of AbstractAssessment subclasses
     *
     * @return string
     */
    public static function getFamilyName(): string
    {
        return AssessmentType::TYPE_LESSON_PLAN;
    }


    /**
     * Lesson Plans never have feedback and thus will not have a normalised mark.
     *
     * @param Participant $participant
     * @param             $publishedOnly
     * @return float|null
     */
    public function getNormalisedMark(Participant $participant, $publishedOnly): ?float
    {
        return null;
    }


    /**
     * Lesson Plans never have responses.
     *
     * @param Participant $participant
     * @param             $publishedOnly
     * @return IResponse|null
     */
    public function getAuthoritativeResponse(Participant $participant, $publishedOnly): ?IResponse
    {
        return null;
    }


    /**
     * Lesson Plans never require responses.
     *
     * @param Participant $participant
     * @return bool
     */
    public function isResponseRequired(Participant $participant): bool
    {
        return false;
    }


    /**
     * Lesson Plans never have feedback.
     *
     * @param Participant $participant
     * @param             $publishedOnly
     * @return IFeedback|null
     */
    public function getAuthoritativeFeedback(Participant $participant, $publishedOnly): ?IFeedback
    {
        return null;
    }


    /**
     * Lesson Plans never have feedback.
     *
     * @param Participant $participant
     * @return bool
     */
    public function isFeedbackRequired(Participant $participant): bool
    {
        return false;
    }


    /**
     * Lesson Planes never have feedback.
     *
     * @param Participant $participant
     * @return bool
     */
    public function hasPendingDraftAuthoritativeFeedback(Participant $participant): bool
    {
        return false;
    }


    /**
     * Check the dates set on this model are valid
     * @throws InvalidArgumentException if the dates are not validly set
     * @return void
     */
    public function assertValidDates(): void
    {
        /* Check publish date against everything else
        /* Don't check dates if in course */
        if ($this->getBaseContext() instanceof UnitSpecification) {
            return;
        }

        $this->assertDateSequence([
            'publish' => $this->getPublishAt(),
            'open'    => $this->getOpenAt(),
            'due'     => $this->getDueAt(),
            'late'    => $this->getCloseAt()
        ]);
    }


    /**
     * @return Collection|null
     */
    public function getAttachments(): ?Collection
    {
        return $this->attachments;
    }


    /**
     * @param Collection|null $attachments
     * @return $this
     */
    public function setAttachments(?Collection $attachments): self
    {
        $this->attachments = $attachments;
        return $this;
    }


    /**
     * The Open date is the same as the Publish date for Lesson Plans.
     *
     * @return \Alaress_Date_Format|null
     */
    public function getOpenAt(): ?\Alaress_Date_Format
    {
        return $this->getPublishAt();
    }


    /**
     * The Close date is the same as the Due date for Lesson Plans.
     *
     * @return \Alaress_Date_Format|null
     */
    public function getCloseAt(): ?\Alaress_Date_Format
    {
        return $this->getDueAt();
    }

    /**
     * @param GenericCollection<IAssessable>|null $linkedAssessments
     */
    public function setLinkedAssessments(?GenericCollection $linkedAssessments): self
    {
        $this->linkedAssessments = $linkedAssessments;
        return $this;
    }

    /**
     * @return GenericCollection<IAssessable>|null
     */
    public function getLinkedAssessments(): ?GenericCollection
    {
        return $this->linkedAssessments;
    }


    /**
     * @return string|null
     */
    public function getLessonPlanDuration(): ?string
    {
        return $this->duration;
    }


    /**
     * @param string|null $duration
     * @return $this
     */
    public function setLessonPlanDuration(?string $duration): self
    {
        $this->duration = $duration;
        return $this;
    }
}
