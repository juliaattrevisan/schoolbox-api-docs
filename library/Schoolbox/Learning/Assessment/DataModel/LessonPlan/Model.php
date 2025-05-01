<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\LessonPlan;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Assessment\DataModel\AssessmentModelTrait;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\User;

class Model implements IAssessableDataModel, ISoftDeletable
{
    use Id;
    use DeletedAtDate;
    use AssessmentModelTrait;

    /**
     * @var int|null
     */
    private ?int $lessonPlanTemplate = null;


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
     * @var string|null
     */
    private ?string $duration = null;


    /**
     * Assessments that are linked to this lesson plan
     */
    private ?GenericCollection $linkedAssessments = null;


    /**
     * @return int|null
     */
    public function getLessonPlanTemplate(): ?int
    {
        return $this->lessonPlanTemplate;
    }


    /**
     * @param int|null $lessonPlanTemplate
     * @return $this
     */
    public function setLessonPlanTemplate(?int $lessonPlanTemplate): self
    {
        $this->lessonPlanTemplate = $lessonPlanTemplate;
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
     * Convenience method to shank in a whole new Field
     *
     * @param Field\Model $field
     * @return Model
     * @throws \UnexpectedValueException
     */
    public function addField(Field\Model $field): self
    {
        $this->getFields()->add($field);
        return $this;
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
     * @return Model
     */
    public function setAttachments(?Collection $attachments): self
    {
        $this->attachments = $attachments;
        return $this;
    }

    /**
     * @param GenericCollection|null $linkedAssessments
     * @return Model
     */
    public function setLinkedAssessments(?GenericCollection $linkedAssessments): self
    {
        $this->linkedAssessments = $linkedAssessments;
        return $this;
    }

    /**
     * @return GenericCollection|null
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
