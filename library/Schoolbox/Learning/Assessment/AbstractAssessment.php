<?php

/**
 * Assessable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Alaress_Date_Format;
use InvalidArgumentException;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Learning\Assessment\WorkType\Model as WorkType;
use Schoolbox\Curriculum\Node;
use Schoolbox\L10n\Mapper as L10nMapper;
use Schoolbox\L10n\RosettaStoneFactory;
use TypeError;

abstract class AbstractAssessment implements IAssessable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;


    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $description;

    /**
     * @var Alaress_Date_Format|null
     */
    private ?Alaress_Date_Format $publishAt = null;

    /**
     * @var Alaress_Date_Format|null
     */
    private ?Alaress_Date_Format $openAt = null;

    /**
     * @var Alaress_Date_Format|null
     */
    private ?Alaress_Date_Format $dueAt = null;

    /**
     * @var Alaress_Date_Format|null
     */
    private ?Alaress_Date_Format $closeAt = null;

    /**
     * @var float
     */
    private $weight;

    /**
     * Number of days an AssessmentSpec is expected to take.
     *
     * This is used to automatically populate dates when importing CourseSpecs.
     *
     * @var int
     */
    private $defaultOpenSpan;


    /**
     * @var bool|null
     */
    private ?bool $courseManaged = null;


    /**
     * A flag denoting whether this assessment requires the SEB Safe Exam Browser
     *
     * @var bool|null
     */
    private ?bool $sebEnabled = null;

    /**
     * @var int|null
     */
    private ?int $originalAssessmentId = null;

    /**
     * If this assessment was cloned from another assessment, this is a reference
     * back to that original assessment.
     *
     * @var IAssessable|null
     */
    private ?IAssessable $originalAssessment = null;
    /**
     * The person who created this Assessable
     *
     * @var User
     */
    private $creator;

    /**
     * @var MarkType
     */
    private $markType;

    /**
     * @var GradeGroup|null
     */
    private $gradeGroup;

    /**
     * @var IMarkScheme
     */
    private $markScheme;


    /**
     * The UnitSpec, UnitImpl, Project or Folder where this Assessment resides.
     *
     * @var AssessmentContext
     */
    private $context;

    /**
     * If this assessment's context is a UnitImpl, it may have been created from a UnitSpec.
     * This is a reference back to the IAssessable from which this assessment was cloned.
     *
     * @var IAssessable|null
     */
    private $specificationAssessment;

    /**
     * If published, then who can see the feedback for this Assessment.
     *
     * One of { MARK_VISIBILITY_PRIVATE | MARK_VISIBILITY_STUDENT | MARK_VISIBILITY_PARENT | MARK_VISIBILITY_PARENT_STUDENT }
     *
     * @var string
     */
    private $markVisibility;


    /**
     * 1-based, not 0-based, to enable the mapper to recognise when it needs auto-assign a value.
     *
     * @var int
     */
    private $sequence;

    /**
     * The "type" of the Due Work (Essay, Homework, etc)
     * NULL is a valid value, and means 'None'
     *
     * @var null|WorkType
     */
    private $workType;

    /**
     * A collection of Curriculum Nodes
     *
     * @var Node\Model[]|GenericCollection
     */
    private $curriculumNodes;

    /**
     * @var Plan[]|GenericCollection
     */
    private $plans;

    /**
     * @var IAssessable[]|GenericCollection
     */
    private $customisedAssessments;


    /**
     * A set of rubric_instance records which contain the settings for each grid associated with this learning activity
     * (if applicable) - actual grid contents need to be obtained separately.
     *
     * @var GenericCollection<\Schoolbox\Assessment\Rubric\Instance\Model>|null
     */
    private ?GenericCollection $rubricInstances;


    /**
     * Assessable constructor.
     */
    public function __construct()
    {
        $this->curriculumNodes = new GenericCollection(Node\Model::class);
        $this->plans = new GenericCollection(Plan::class);
        $this->customisedAssessments = new GenericCollection(IAssessable::class);
        $this->rubricInstances = new GenericCollection(\Schoolbox\Assessment\Rubric\Instance\Model::class);
    }


    /**
     * George Cloney
     */
    public function __clone()
    {
        $this->setPublishAt($this->getPublishAt() instanceof Alaress_Date_Format ? clone $this->getPublishAt() : null);
        $this->setOpenAt($this->getOpenAt() instanceof Alaress_Date_Format ? clone $this->getOpenAt() : null);
        $this->setDueAt($this->getDueAt() instanceof Alaress_Date_Format ? clone $this->getDueAt() : null);
        $this->setCloseAt($this->getCloseAt() instanceof Alaress_Date_Format ? clone $this->getCloseAt() : null);

        $this->plans = $this->getPlans()->emptyClone();

        /* TODO: Dump away all the attached files?
         */

        $this->setCreatedAt($this->getCreatedAt() instanceof Alaress_Date_Format ? clone $this->getCreatedAt() : null);
        $this->setUpdatedAt($this->getUpdatedAt() instanceof Alaress_Date_Format ? clone $this->getUpdatedAt() : null);
        $this->setDeletedAt($this->getDeletedAt() instanceof Alaress_Date_Format ? clone $this->getDeletedAt() : null);
    }


    /**
     * @return string
     */
    public static function getDefaultTypeName(): string
    {
        return 'Assessment';
    }


    /**
     * @inheritdoc
     */
    public static function getDefaultTypeNameTranslated(): string
    {
        $rosettaStone = (new RosettaStoneFactory())->build(L10nMapper::getInstance());

        // If the assessment is a terminology term, use that
        if ($rosettaStone->__isset(static::getFamilyName())) {
            return $rosettaStone->translate(static::getFamilyName(), false, true);
        }

        // Otherwise, use the default type name
        return static::getDefaultTypeName();
    }


    /**
     * Clones this object in preparation for persisting as a copy of this object.
     *
     * @return static
     */
    public function cloneForCopy()
    {
        $clone = clone $this;
        $clone->setId(null);

        // Non-specs may now be used as the basis for a clone.
        if ($this->isSpecification()) {
            $clone->setSpecificationAssessment($this);
        }

        $clone->setSequence(null);
        $clone->setCreatedAt(null);

        // This should not inherit any of the spec assessment's participants.
        // (One wonders why spec assessments even have plans?)
        $clone->setPlans($clone->getPlans()->emptyClone());
        return $clone;
    }

    /**
     * @inheritdoc
     */
    public function overwrite(IAssessable $source): IAssessable
    {
        $this->assertTypeMatch($source);
        // Just set every field to the value from `$source`.
        // Exceptions:
        // * ID
        // * Context
        // * Creator
        // * Plans
        // * Spec assessment
        $this->courseManaged   = $source->getCourseManaged();
        $this->curriculumNodes = $source->getCurriculumNodes();
        $this->defaultOpenSpan = $source->getDefaultOpenSpan();
        $this->description     = $source->getDescription();
        $this->gradeGroup      = $source->getGradeGroup();
        $this->markScheme      = $source->getMarkScheme();
        $this->markType        = $source->getMarkType();
        $this->markVisibility  = $source->getMarkVisibility();
        $this->sebEnabled      = $source->getSebEnabled();
        $this->sequence        = $source->getSequence();
        $this->title           = $source->getTitle();
        $this->weight          = $source->getWeight();
        $this->workType        = $source->getWorkType();
        $this->rubricInstances = $source->getRubricInstances();

        // Dates are cloned
        $this->publishAt = $source->getPublishAt() ? clone $source->getPublishAt() : null;
        $this->openAt    = $source->getOpenAt()    ? clone $source->getOpenAt()    : null;
        $this->dueAt     = $source->getDueAt()     ? clone $source->getDueAt()     : null;
        $this->closeAt   = $source->getCloseAt()   ? clone $source->getCloseAt()   : null;
        $this->createdAt = $source->getCreatedAt() ? clone $source->getCreatedAt() : null;
        $this->updatedAt = $source->getUpdatedAt() ? clone $source->getUpdatedAt() : null;
        $this->deletedAt = $source->getDeletedAt() ? clone $source->getDeletedAt() : null;
        return $this;
    }


    /**
     * @inheritdoc
     */
    public function getBaseAssessable(): IAssessable
    {
        if ($this->context instanceof Project) {
            // Recursively climb until we reach a base assessment
            return $this->context->getBaseAssessable();
        }
        return $this;
    }


    /**
     * @return bool
     * @throws InsufficientHydrationException
     */
    public function isBaseAssessable(): bool
    {
        if (null === $this->context) {
            throw new InsufficientHydrationException('Cannot determine if this assessable is a base assessable if context is not hydrated');
        }
        return !$this->context instanceof Project;
    }

    /* Delegation Methods
     */

    /**
     * Is this user a participant in this assessment?
     *
     * @param User $user
     * @return bool
     */
    public function isParticipant(User $user): bool
    {
        $candidate = $this->getParticipantIfExtant($user);

        return $candidate instanceof Participant;
    }


    /**
     * Find a reference to a particular Participant in this Assessable
     * regardless of excluded or deleted
     * which saves you this traversal in Controllers etc
     *
     * @param User $user
     * @return Participant
     * @throws \UnexpectedValueException
     */
    public function getParticipant(User $user)
    {
        $userId = $user->getId();
        foreach ($this->plans as $plan) {
            if ($plan->getParticipants()->offsetExists($userId)) {
                return $plan->getParticipants()[$userId];
            }
        }

        $fullName = "{$user->getFirstname()} {$user->getLastname()}";
        throw new \UnexpectedValueException(
            "{$fullName} (id: {$user->getId()}) is not a participant of {$this->getTitle()} (id: {$this->getId()})"
        );
    }


    /**
     * @inheritdoc
     */
    public function getParticipantIfExtant(User $user, $removeExcluded = true): ?Participant
    {
        if ($user->isDeleted()) {
            return null;
        }
        $userId = $user->getId();
        foreach ($this->plans as $plan) {
            if ($plan->getParticipants()->offsetExists($userId)) {
                $participant = $plan->getParticipants()[$userId];
                if ($removeExcluded && $participant->isExcluded()) {
                    continue;
                }

                return $participant;
            }
        }

        return null;
    }


    /**
     * @param User $user
     * @return bool
     */
    public function unsetUserParticipation(User $user): bool
    {
        $userRemoved = false;
        foreach ($this->plans as $plan) {
            foreach ($plan->getParticipants() as $idx => $participant) {
                if ((int)$participant->getUser()->getId() === (int)$user->getId()) {
                    $plan->getParticipants()->offsetUnset($idx);
                    $userRemoved = true;
                }
            }
        }
        return $userRemoved;
    }

    /**
     * @inheritdoc
     */
    public function isPublished(?User $student, ?Alaress_Date_Format $now = null): bool
    {
        if (null === $now) {
            $now = Alaress_Date_Format::now();
        }

        // Intentionally calls getter rather than direct access to enable assessment-specific date acrobatics
        $participant = $student ? $this->getParticipantIfExtant($student) : null;
        $publishDate = $participant ? $participant->getPublishDate() : $this->getPublishAt();

        // A null publish date means the assessment has not been published.
        if (null === $publishDate) {
            return false;
        }

        return $now->gte($publishDate);
    }


    /**
     * @inheritdoc
     */
    public function isOpen(User $student, ?Alaress_Date_Format $now = null): bool
    {
        $participant = $this->getParticipantIfExtant($student);

        if ($participant) {
            $openDate = $participant->getOpenDate();
            $closeDate = $participant->getCloseDate();
        } else {
            // Intentionally uses getters to enable Task null date coalescence
            $openDate =  $this->getOpenAt();
            $closeDate = $this->getCloseAt();
        }

        if (null === $now) {
            $now = Alaress_Date_Format::now();
        }

        return $now->gte($openDate) && $now->lt($closeDate);
    }


    /**
     * @inheritdoc
     */
    public function isOverdue(?Alaress_Date_Format $now = null)
    {
        if (null === $now) {
            $now = Alaress_Date_Format::now();
        }

        // Intentionally calls getter rather than direct access to enable assessment-specific date acrobatics
        return $now->gte($this->getDueAt()) && $now->lt($this->getCloseAt());
    }


    /**
     * @inheritdoc
     */
    public function isPastClose(?Alaress_Date_Format $now = null)
    {
        if (null === $now) {
            $now = Alaress_Date_Format::now();
        }

        // Intentionally calls getter rather than direct access to enable assessment-specific date acrobatics
        return $now->gte($this->getCloseAt());
    }


    /**
     * By default, any instructor may create a response for any participating
     * student.
     *
     * Otherwise, they may create a response for themselves only if the
     * assessment has a dynamic plan.
     *
     * @param User $instructor
     * @param User $submittedOnBehalfOf
     * @return bool
     */
    public function canInstructorCreateResponse(User $instructor, User $submittedOnBehalfOf)
    {
        // Specified student not a participant?
        $participant = $this->getParticipantIfExtant($submittedOnBehalfOf);
        if ($participant) {
            return true;
        }

        // Is instructor wishing to create response for themselves?
        if ($instructor->getId() == $submittedOnBehalfOf->getId()) {
            // If no participation, must be a dynamic plan available
            return $this->hasDynamicPlan();
        }

        return false;
    }


    /* Traversals
     */

    /**
     * Intentionally checks ID, not just type, for cases such as project where sub-assessable set is relevant.
     *
     * @param Participant $participant
     * @throws \DomainException If participant's assessment is not this assessment
     */
    protected function assertSameAssessment(Participant $participant)
    {
        if ($this->id !== $participant->getAssessable()->getId()) {
            $otherId = $participant->getAssessable()->getId();
            throw new \DomainException("Participant assessment ({$otherId}) must match this assessment ({$this->id}) to ensure assessment-type-specific logic is reliably invoked");
        }
    }

    /**
     * Assert that `$this` and `$other` are of the same type.
     *
     * @param IAssessable $other
     * @return void
     * @throws TypeError if they are not
     */
    protected function assertTypeMatch(IAssessable $other): void
    {
        if (!($this instanceof $other)) {
            throw new TypeError(
                sprintf(
                    'Assessment type mismatch: %s does not match %s',
                    $this::class,
                    $other::class,
                )
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function isResponseRequired(Participant $participant)
    {
        $this->assertSameAssessment($participant);

        $hasSubmittedResponse = false;
        foreach ($participant->getAllResponses() as $response) {
            if ($response->isSubmitted()) {
                $hasSubmittedResponse = true;
                break;
            }
        }

        $hasPublishedFeedback = (null !== $participant->findMostRecentInstructorGivenPublishedFeedback());

        // No Submitted Responses and No Published Feedback?
        return !$hasSubmittedResponse && !$hasPublishedFeedback;
    }


    /**
     * Most assessment types don't implement the resubmission state:
     * therefore this will always return false.
     *
     * @inheritdoc
     */
    public function isResubmissionRequired(Participant $participant): bool
    {
        $this->assertSameAssessment($participant);

        return false;
    }


    /**
     * @inheritdoc
     */
    public function isReviewComplete(Participant $participant)
    {
        $this->assertSameAssessment($participant);

        return $participant->hasPublishedFeedback() && !$participant->isFeedbackRequired();
    }


    /**
     * @inheritdoc
     */
    public function hasSubmittedResponse(Participant $participant)
    {
        $this->assertSameAssessment($participant);

        foreach ($participant->getAllResponses() as $response) {
            if ($response->isSubmitted()) {
                return true;
            }
        }
        return false;
    }


    /**
     * @inheritdoc
     */
    public function hasPublishedFeedback(Participant $participant)
    {
        $this->assertSameAssessment($participant);

        return (null !== $participant->findMostRecentInstructorGivenPublishedFeedback());
    }


    /**
     * @inheritdoc
     */
    public function hasMark(Participant $participant)
    {
        $this->assertSameAssessment($participant);

        // Default case
        return $this->hasPublishedFeedback($participant);
    }


    /**
     * @inheritdoc
     */
    public function getHumanReadableMark(Participant $participant, $publishedOnly)
    {
        $normalisedMark        = $this->getNormalisedMark($participant, $publishedOnly);
        $authoritativeResponse = $this->getAuthoritativeResponse($participant, $publishedOnly);

        return $this->getMarkScheme()->getHumanReadableMark($normalisedMark, $authoritativeResponse);
    }


    /**
     * @inheritdoc
     */
    public function getHumanReadableInstructorMark(Participant $participant, $publishedOnly)
    {
        // For all non due work/reporting assessments, this falls back to getHumanReadableMark (the models override this)
        // This avoids having to do a ton of instanceof checks everywhere and as I expect we will introduce
        // this functionality for more assessment types in the future, this makes it easier to do so.
        // ^ oh look, we did. although definitely have thoughts on how this was approached now 1.5 years later...

        return $this->getHumanReadableMark($participant, $publishedOnly);
    }


    /**
     * Is feedback on this assessment visible to $viewingUser?
     *
     * @param User $viewingUser
     * @return bool
     */
    public function isFeedbackVisibleToUser(User $viewingUser)
    {
        return $this->isFeedbackVisibleToRoleType($viewingUser->getRole()->getType());
    }


    /**
     * @inheritDoc
     */
    public function isFeedbackVisibleToRoleType(string $roleType): bool
    {
        switch ($this->getMarkVisibility()) {
            case self::MARK_VISIBILITY_PARENT_STUDENT:
                return $roleType === Role::STAFF || $roleType === Role::STUDENT || $roleType === Role::PARENT;

            case self::MARK_VISIBILITY_STUDENT:
                return $roleType === Role::STAFF || $roleType === Role::STUDENT;

            case self::MARK_VISIBILITY_PARENT:
                return $roleType === Role::STAFF || $roleType === Role::PARENT;

            case self::MARK_VISIBILITY_PRIVATE:
            default:
                return $roleType === Role::STAFF;
        }
    }


    /**
     * Returns the number of responses submitted to this assessment.
     *
     * (includes sub-assessment counts for projects)
     *
     * @param bool $includeDrafts
     * @return int
     */
    public function getCountResponses(bool $includeDrafts = false): int
    {
        $count = 0;
        foreach ($this->getAllParticipants() as $participant) {
            if ($includeDrafts) {
                $count += $participant->getAllResponses()->count();
            } else {
                foreach ($participant->getAllResponses() as $response) {
                    if ($response->isSubmitted()) {
                        $count++;
                    }
                }
            }
        }
        return $count;
    }


    /**
     * Returns the number of feedbacks given on this assessment
     *
     * (includes sub-assessment counts for projects)
     *
     * @param bool $includeDrafts
     * @return int
     */
    public function getCountFeedbacks(bool $includeDrafts = false): int
    {
        $count = 0;
        foreach ($this->getAllParticipants() as $participant) {
            if ($includeDrafts) {
                $count += $participant->getAllFeedbacks()->count();
            } else {
                foreach ($participant->getAllFeedbacks() as $feedback) {
                    if ($feedback->isPublished()) {
                        $count++;
                    }
                }
            }
        }
        return $count;
    }


    /**
     * Returns the number of interactions in this assessment.
     * An interaction is the generic word we use for "responses or feedbacks"
     * @param bool $includeDrafts
     * @return int
     */
    public function getCountInteractions(bool $includeDrafts = false): int
    {
        return $this->getCountResponses($includeDrafts) + $this->getCountFeedbacks($includeDrafts);
    }


    /**
     * The friendly, uninformative version of assertValidDates
     *
     * @return bool
     */
    public function isValidDates()
    {
        try {
            $this->assertValidDates();
            return true;
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * By default: all assessments are usable.
     */
    public function isUsable(): bool
    {
        return true;
    }

    /**
     * By default: all assessments are usable.
     */
    public function assertUsable(): void
    {
    }

    /**
     * @inheritdoc
     */
    public function isSpecification()
    {
        return $this->getContext()->isSpecificationContext();
    }


    /**
     * @inheritdoc
     */
    public function isImplementation()
    {
        return $this->getContext()->isImplementationContext();
    }


    /**
     * Verify the given dates are all non-null and all in the given sequence
     *
     * @param Alaress_Date_Format[] $dates Indexed by their display name, sequenced in expected order
     * @throws InvalidArgumentException if any value is absent, or values are out of order
     * @return void
     */
    protected function assertDateSequence(array $dates): void
    {
        $previousAdf   = null;
        $previousLabel = '';

        foreach ($dates as $label => $adf) {
            if (!$adf instanceof Alaress_Date_Format) {
                throw new InvalidArgumentException("Please set a {$label} date");
            }

            if ($previousAdf && $previousAdf->gt($adf)) {
                throw new InvalidArgumentException("Please set the {$previousLabel} date to be earlier than the $label date");
            }

            // cache the previous iteration
            $previousAdf   = $adf;
            $previousLabel = $label;
        }
    }

    /**
     * @inheritdoc
     */
    public function getTypeName()
    {
        // If a work type is set, use that
        if ($this->getWorkType()) {
            return $this->getWorkType()->getName();
        }

        // Otherwise, use the default type name (translated if in terminology)
        return static::getDefaultTypeNameTranslated();
    }


    /* Getters/Setters
     */

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }


    /**
     * @param string $title
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }


    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }


    /**
     * @param string $description
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }


    /**
     * @return Alaress_Date_Format|null
     */
    public function getPublishAt(): ?Alaress_Date_Format
    {
        return $this->publishAt;
    }


    /**
     * @param Alaress_Date_Format|null $publishAt
     * @return $this
     */
    public function setPublishAt(?Alaress_Date_Format $publishAt): self
    {
        $this->publishAt = $publishAt;
        return $this;
    }


    /**
     * @return Alaress_Date_Format|null
     */
    public function getOpenAt(): ?Alaress_Date_Format
    {
        return $this->openAt;
    }


    /**
     * @param Alaress_Date_Format|null $openAt
     * @return $this
     */
    public function setOpenAt(?Alaress_Date_Format $openAt): self
    {
        $this->openAt = $openAt;
        return $this;
    }


    /**
     * @return Alaress_Date_Format|null
     */
    public function getDueAt(): ?Alaress_Date_Format
    {
        return $this->dueAt;
    }


    /**
     * @param Alaress_Date_Format|null $dueAt
     * @return $this
     */
    public function setDueAt(?Alaress_Date_Format $dueAt): self
    {
        $this->dueAt = $dueAt;
        return $this;
    }


    /**
     * @return Alaress_Date_Format|null
     */
    public function getCloseAt(): ?Alaress_Date_Format
    {
        return $this->closeAt;
    }


    /**
     * @param Alaress_Date_Format|null $closeAt
     * @return $this
     */
    public function setCloseAt(?Alaress_Date_Format $closeAt): self
    {
        $this->closeAt = $closeAt;
        return $this;
    }


    /**
     * @return float
     */
    public function getWeight()
    {
        return $this->weight;
    }


    /**
     * @param float $weight
     * @return $this
     */
    public function setWeight($weight)
    {
        $this->weight = $weight;
        return $this;
    }


    /**
     * @return int
     */
    public function getDefaultOpenSpan()
    {
        return $this->defaultOpenSpan;
    }


    /**
     * @param int $defaultOpenSpan
     * @return $this
     */
    public function setDefaultOpenSpan($defaultOpenSpan)
    {
        $this->defaultOpenSpan = $defaultOpenSpan;
        return $this;
    }


    /**
     * @return bool|null
     */
    public function getCourseManaged(): ?bool
    {
        return $this->courseManaged;
    }


    /**
     * @param bool|null $courseManaged
     * @return $this
     */
    public function setCourseManaged(?bool $courseManaged): self
    {
        $this->courseManaged = $courseManaged;

        return $this;
    }


    /**
     * @return bool|null
     */
    public function getSebEnabled(): ?bool
    {
        return $this->sebEnabled;
    }


    /**
     * @param bool|null $sebEnabled
     * @return AbstractAssessment
     */
    public function setSebEnabled(?bool $sebEnabled): self
    {
        $this->sebEnabled = $sebEnabled;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getOriginalAssessmentId(): ?int
    {
        return $this->originalAssessmentId;
    }

    public function getOriginalAssessment(): ?IAssessable
    {
        return $this->originalAssessment;
    }

    /**
     * @param int|null $originalAssessmentId
     * @return self
     */
    public function setOriginalAssessmentId(?int $originalAssessmentId): self
    {
        $this->originalAssessmentId = $originalAssessmentId;
        return $this;
    }

    public function setOriginalAssessment(?IAssessable $originalAssessment): self
    {
        $this->originalAssessment = $originalAssessment;
        return $this;
    }

    /**
     * @return User
     */
    public function getCreator()
    {
        return $this->creator;
    }


    /**
     * @param User $creator
     * @return $this
     */
    public function setCreator($creator)
    {
        $this->creator = $creator;
        return $this;
    }


    /**
     * Returns all Participants in this IAssessable, indexed by user.id
     *
     * @return Collection|Participant[] Indexed by user.id
     */
    public function getAllParticipants()
    {
        $participants = new GenericCollection(Participant::class);
        foreach ($this->plans as $plan) {
            foreach ($plan->getParticipants() as $participant) {
                // Note we cannot index by participant.id, as that may be NULL
                $participants[$participant->getUser()->getId()] = $participant;
            }
        }
        return $participants->sortByCallback(function (Participant $p1, Participant $p2) {
            $format = '{lastname},{firstname}';
            return strcasecmp($p1->getUser()->formatName($format), $p2->getUser()->formatName($format));
        }, true);
    }


    /**
     * Get all users who are participating in this IAssessable, indexed by user.id
     *
     * @return User[]|GenericCollection Indexed by user.id
     */
    public function getParticipantUsers(): GenericCollection
    {
        $participatingUsers = new GenericCollection(User::class);
        foreach ($this->plans as $plan) {
            foreach ($plan->getParticipants() as $participant) {
                if ($participant->isExcluded()) {
                    continue;
                }
                $user                               = $participant->getUser();
                $participatingUsers[$user->getId()] = $user;
            }
        }
        return $participatingUsers;
    }


    /**
     * @return MarkType
     */
    public function getMarkType()
    {
        return $this->markType;
    }


    /**
     * @param MarkType $markType
     * @return static
     */
    public function setMarkType(MarkType $markType)
    {
        $this->markType = $markType;
        return $this;
    }


    /**
     * @return GradeGroup|null
     */
    public function getGradeGroup()
    {
        return $this->gradeGroup;
    }


    /**
     * Default behaviour is not to permit nulls. Sub-classes can override this (eg Quiz + Task)
     *
     * @param GradeGroup $gradeGroup
     * @return static
     */
    public function setGradeGroup(GradeGroup $gradeGroup)
    {
        $this->gradeGroup = $gradeGroup;
        return $this;
    }


    /**
     * @return IMarkScheme
     */
    public function getMarkScheme()
    {
        return $this->markScheme;
    }


    /**
     * @param IMarkScheme $markScheme
     * @return static
     */
    public function setMarkScheme(IMarkScheme $markScheme)
    {
        $this->markScheme = $markScheme;
        return $this;
    }


    /**
     * @param AssessmentContext $context
     * @return static
     */
    public function setContext(AssessmentContext $context)
    {
        $this->context = $context;
        return $this;
    }


    /**
     * @return AssessmentContext
     */
    public function getContext()
    {
        return $this->context;
    }


    /** Retrieves the parent context of this project
     *
     * @return AssessmentContext
     */
    public function getBaseContext()
    {
        return $this->getContext()->getBaseContext();
    }


    /**
     * @return string
     */
    public function getMarkVisibility()
    {
        return $this->markVisibility;
    }


    /**
     * @param string $markVisibility
     * @return static
     */
    public function setMarkVisibility($markVisibility)
    {
        $this->markVisibility = $markVisibility;
        return $this;
    }


    /**
     * @return null|IAssessable
     */
    public function getSpecificationAssessment()
    {
        return $this->specificationAssessment;
    }


    /**
     * @param IAssessable|null $specification
     * @return static
     */
    public function setSpecificationAssessment(?IAssessable $specification = null)
    {
        $this->specificationAssessment = $specification;
        return $this;
    }


    /**
     * @return int
     */
    public function getSequence()
    {
        return $this->sequence;
    }


    /**
     * @param int $sequence
     * @return static
     */
    public function setSequence($sequence)
    {
        $this->sequence = $sequence;
        return $this;
    }


    /**
     * @return null|WorkType
     */
    public function getWorkType()
    {
        return $this->workType;
    }


    /**
     * @param WorkType|null $workType
     * @return static
     */
    public function setWorkType(?WorkType $workType = null)
    {
        $this->workType = $workType;
        return $this;
    }


    /**
     * @return Node\Model[]|GenericCollection
     */
    public function getCurriculumNodes(): GenericCollection
    {
        return $this->curriculumNodes;
    }


    /**
     * @param Node\Model[]|GenericCollection $curriculumNodes
     * @return $this
     */
    public function setCurriculumNodes(GenericCollection $curriculumNodes): IAssessable
    {
        $this->curriculumNodes->replaceWith($curriculumNodes);
        return $this;
    }


    /**
     * @return GenericCollection<\Schoolbox\Assessment\Rubric\Instance\Model>|null
     */
    public function getRubricInstances(): ?GenericCollection
    {
        return $this->rubricInstances;
    }


    /**
     * This variant of a setters also checks/asserts the contents of $rubricInstances supplied here to ensure they're
     * the same type as what it originally was.
     *
     * @param GenericCollection<\Schoolbox\Assessment\Rubric\Instance\Model>|null $rubricInstances
     * @return $this
     */
    public function setRubricInstances(?GenericCollection $rubricInstances): self
    {
        $this->rubricInstances->replaceWith($rubricInstances);
        return $this;
    }


    /**
     * Returns just the Curriculum nodes that do not have an empty code.
     * usually these are parent objects, or titles
     *
     * @return Node\Model[]|GenericCollection
     */
    public function getViewableCurriculumNodes(): GenericCollection
    {
        return $this->curriculumNodes->filter(
            function ($node) {
                if ($node->getCode() !== '') {
                    return true;
                } else {
                    return false;
                }
            }
        );
    }


    /**
     * @return Plan[]|GenericCollection
     */
    public function getPlans(): GenericCollection
    {
        return $this->plans;
    }


    /**
     * @param Plan[]|GenericCollection $plans
     * @return $this
     */
    public function setPlans(GenericCollection $plans): IAssessable
    {
        $this->plans->replaceWith($plans);
        return $this;
    }

    public function setCustomisedAssessments(GenericCollection $customisedAssessments): IAssessable
    {
        $this->customisedAssessments->replaceWith($customisedAssessments);
        return $this;
    }

    public function getCustomisedAssessments(): GenericCollection
    {
        return $this->customisedAssessments;
    }


    /**
     * @inheritDoc
     */
    public function isCustomParticipationModifiable(): bool
    {
        /* If the assessment is a customised assessment then shouldn't allow customisation from it */
        if (null !== $this->getOriginalAssessmentId()) {
            return false;
        }

        /* Custom participation isn't available for specifications */
        if ($this->isSpecification()) {
            return false;
        }

        return true;
    }


    /**
     * @inheritDoc
     */
    public function isCustomParticipationCopyable(): bool
    {
        /* Must be able to modify */
        if (!$this->isCustomParticipationModifiable()) {
            return false;
        }

        return true;
    }


    /**
     * @return null|Plan
     */
    public function getDynamicPlan(): ?Plan
    {
        foreach ($this->plans as $plan) {
            if ($plan->isDynamic()) {
                return $plan;
            }
        }
        return null;
    }


    /**
     * Returns whether this assessment has a dynamic plan
     *
     * @return bool
     */
    public function hasDynamicPlan(): bool
    {
        return (bool)$this->getDynamicPlan();
    }


    /**
     * @inheritdoc
     */
    public function getResponseClass()
    {
        return IResponse::class;
    }


    /**
     * @inheritdoc
     */
    public function getFeedbackClass()
    {
        return IFeedback::class;
    }
}
