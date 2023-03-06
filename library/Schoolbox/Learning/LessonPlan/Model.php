<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\LessonPlan;

use InvalidArgumentException;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\AbstractAssessment;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\User;

class Model extends AbstractAssessment
{
    /**
     * @var \Schoolbox\Admin\LessonPlan\Template\Model|null
     */
    private ?\Schoolbox\Admin\LessonPlan\Template\Model $lessonPlanTemplate = null;


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
     * @return \Schoolbox\Admin\LessonPlan\Template\Model|null
     */
    public function getLessonPlanTemplate(): ?\Schoolbox\Admin\LessonPlan\Template\Model
    {
        return $this->lessonPlanTemplate;
    }


    /**
     * @param \Schoolbox\Admin\LessonPlan\Template\Model|null $lessonPlanTemplate
     * @return Model
     */
    public function setLessonPlanTemplate(?\Schoolbox\Admin\LessonPlan\Template\Model $lessonPlanTemplate): self
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
    public function setAssessmentModel(AssessmentModel $assessmentModel = null): self
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
     * @param Field\Model $field
     * @return $this
     * @throws \UnexpectedValueException
     */
    public function addField(Field\Model $field): self
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
     * This model's "kind of assessment" used to identify it
     * among the family of AbstractAssessment subclasses
     *
     * @return string
     */
    public function getFamilyName(): string
    {
        return 'lessonPlan';
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
}
