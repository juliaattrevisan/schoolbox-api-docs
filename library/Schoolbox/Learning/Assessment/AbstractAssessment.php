<?php

/**
 * Assessable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;

use Alaress_Date_Format;
use Alaress_Date_NullDate;
use InvalidArgumentException;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use \Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use \Schoolbox\User;
use \Schoolbox\Mapper\Traits\Model\Id;
use \Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use \Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use \Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Learning\Assessment\WorkType\Model as WorkType;

abstract class AbstractAssessment implements IAssessable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * Default type name for this assessment.
     *
     * @var string
     */
    protected $defaultTypeName = 'Assessment';

    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $description;

    /**
     * @var Alaress_Date_Format
     */
    private $publishAt;

    /**
     * @var Alaress_Date_Format
     */
    private $openAt;

    /**
     * @var Alaress_Date_Format
     */
    private $dueAt;

    /**
     * @var Alaress_Date_Format
     */
    private $closeAt;

    /**
     * @var int
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
     * A Collection of Participant instances
     *
     * @var Collection|Participant[]
     */
    private $allParticipants;

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
     * @var Collection|IResponse[]
     */
    private $responses;

    /**
     * @var Collection|IFeedback[]
     */
    private $feedbacks;

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
     * One of { MARK_VISIBILITY_PRIVATE | MARK_VISIBILITY_STUDENT | MARK_VISIBILITY_PARENT_STUDENT }
     *
     * @var int
     */
    private $markVisibility;

    /**
     * Cache to assist in finding Participants for User object
     * @var array
     */
    private $participantCache = [];

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
     * @var Collection
     */
    private $curriculumNodes;

    /**
     * Assessable constructor.
     */
    public function __construct()
    {
        $this->setAllParticipants(new Collection());
        $this->responses = new GenericCollection($this->getResponseClass());
        $this->feedbacks = new GenericCollection($this->getFeedbackClass());
        $this->setCurriculumNodes(new Collection());
    }

    /**
     * George Cloney
     */
    public function __clone()
    {
        $this->setPublishAt($this->getPublishAt() instanceof Alaress_Date_Format ? clone $this->getPublishAt() : new Alaress_Date_NullDate());
        $this->setOpenAt($this->getOpenAt() instanceof Alaress_Date_Format ? clone $this->getOpenAt() : new Alaress_Date_NullDate());
        $this->setDueAt($this->getDueAt() instanceof Alaress_Date_Format ? clone $this->getDueAt() : new Alaress_Date_NullDate());
        $this->setCloseAt($this->getCloseAt() instanceof Alaress_Date_Format ? clone $this->getCloseAt() : new Alaress_Date_NullDate());

        /* Explicitly dump away all the Participants
         */
        $this->setAllParticipants(new Collection());

        /* TODO: Dump away all the attached files?
         */

        $this->setCreatedAt($this->getCreatedAt() instanceof Alaress_Date_Format ? clone $this->getCreatedAt() : new Alaress_Date_NullDate());
        $this->setUpdatedAt($this->getUpdatedAt() instanceof Alaress_Date_Format ? clone $this->getUpdatedAt() : new Alaress_Date_NullDate());
        $this->setDeletedAt($this->getDeletedAt() instanceof Alaress_Date_Format ? clone $this->getDeletedAt() : new Alaress_Date_NullDate());
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
        $clone->setSpecificationAssessment($this);
        $clone->setSequence(null);
        return $clone;
    }

    /* Delegation Methods
     */

    /**
     * Is this user a participant in this assessment?
     *
     * @param User $user
     * @return bool
     */
    public function isParticipant(User $user)
    {
        return ($this->findParticipantIfExtant($user) instanceof Participant);
    }

    /**
     * Find a reference to a particular Participant in this Assessable
     * which saves you this traversal in Controllers etc
     *
     * @param User $user
     * @return Participant
     * @throws \UnexpectedValueException
     */
    public function getParticipant(User $user)
    {
        $participant = $this->findParticipantIfExtant($user);
        if ($participant instanceof Participant) {
            return $participant;
        }
        throw new \UnexpectedValueException("{$user->getFullName()} {$user->getId()} is not a participant of {$this->getTitle()}");
    }


    /**
     * @inheritdoc
     */
    public function getParticipantIfExtant(User $user)
    {
        return $this->findParticipantIfExtant($user);
    }


    /**
     * Find a reference to a particular Participant in this Assessable, if they exist.
     *
     * This was added for optimization reasons - it's far more performant to check if
     * a value is null than to throw and immediately catch an exception.
     *
     * @param User $user
     * @return Participant|null
     */
    protected function findParticipantIfExtant(User $user )
    {
        $userId = $user->getId();

        /** @var Participant $currentParticipant */
        foreach ($this->getAllParticipants() as $i => $currentParticipant) {
            if ($currentParticipant->getUser()
                                   ->getId() == $userId
            ) {
                $this->participantCache[$userId] = $i;
                return $currentParticipant;
            }
        }

        return null;
    }


    /**
     * @inheritdoc
     */
    public function isPublished(Alaress_Date_Format $now = null)
    {
        if (null === $now || $now->isNull()) {
            $now = Alaress_Date_Format::now();
        }

        // Intentionally calls getter rather than direct access to enable assessment-specific date acrobatics
        return $now->gte($this->getPublishAt());
    }


    /**
     * @inheritdoc
     */
    public function isOpen(Alaress_Date_Format $now = null)
    {
        if (null === $now || $now->isNull()) {
            $now = Alaress_Date_Format::now();
        }

        // Intentionally calls getter rather than direct access to enable assessment-specific date acrobatics
        return $now->gte($this->getOpenAt()) && $now->lt($this->getCloseAt());
    }


    /**
     * @inheritdoc
     */
    public function isOverdue(Alaress_Date_Format $now = null)
    {
        if (null === $now || $now->isNull()) {
            $now = Alaress_Date_Format::now();
        }

        // Intentionally calls getter rather than direct access to enable assessment-specific date acrobatics
        return $now->gte($this->getDueAt()) && $now->lt($this->getCloseAt());
    }


    /**
     * @inheritdoc
     */
    public function isPastClose(Alaress_Date_Format $now = null)
    {
        if (null === $now || $now->isNull()) {
            $now = Alaress_Date_Format::now();
        }

        // Intentionally calls getter rather than direct access to enable assessment-specific date acrobatics
        return $now->gte($this->getCloseAt());
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
        if($this->id !== $participant->getAssessable()->getId()){
            $otherId = $participant->getAssessable()->getId();
            throw new \DomainException("Participant assessment ({$otherId}) must match this assessment ({$this->id}) to ensure assessment-type-specific logic is reliably invoked");
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
        $hasPublishedFeedback = false;
        foreach ($participant->getAllFeedbacks() as $feedback) {
            if ($feedback->isPublished()) {
                $hasPublishedFeedback = true;
                break;
            }
        }

        // No Submitted Responses and No Published Feedback?
        return !$hasSubmittedResponse && !$hasPublishedFeedback;
    }


    /**
     * @inheritdoc
     */
    public function isReviewComplete(Participant $participant)
    {
        $this->assertSameAssessment($participant);

        return  $participant->hasPublishedFeedback() && !$participant->isFeedbackRequired();
    }

    /**
     * @inheritdoc
     */
    public function hasSubmittedResponse(Participant $participant)
    {
        $this->assertSameAssessment($participant);

        foreach($participant->getAllResponses() as $response){
            if($response->isSubmitted()){
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

        foreach($participant->getAllFeedbacks() as $feedback){
            if($feedback->isPublished()){
                return true;
            }
        }
        return false;
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
     * Is feedback on this assessment visible to $viewingUser?
     *
     * @param User $viewingUser
     * @return bool
     */
    public function isFeedbackVisibleToUser(User $viewingUser)
    {
        $role = $viewingUser->getRole();

        switch ($this->getMarkVisibility()) {
            case self::MARK_VISIBILITY_PARENT_STUDENT:
                return $role->isStaff() || $role->isStudent() || $role->isParent();

            case self::MARK_VISIBILITY_STUDENT:
                return $role->isStaff() || $role->isStudent();

            case self::MARK_VISIBILITY_PRIVATE:
            default:
                return $role->isStaff();
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
        foreach ($this->allParticipants as $participant) {
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
        foreach ($this->allParticipants as $participant) {
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
     * @param Alaress_Date_Format[] $dates, indexed by their display name, sequenced in expected order
     * @throws InvalidArgumentException if any value is absent, or values are out of order
     * @return void
     */
    protected function assertDateSequence(array $dates)
    {
        /** @var Alaress_Date_Format $previousAdf */
        $previousAdf = null;
        $previousLabel = '';

        foreach ($dates as $label => $adf) {
            if ($adf->isNull()) {
                throw new InvalidArgumentException("set a $label date");
            }

            if ($previousAdf && $previousAdf->gt($adf)) {
                throw new InvalidArgumentException("set the $previousLabel date to be earlier than the $label date");
            }

            // cache the previous iteration
            $previousAdf = $adf;
            $previousLabel = $label;
        }
    }

    /**
     * @inheritdoc
     */
    public function getTypeName()
    {
        $subTypeName = $this->getWorkType() ? $this->getWorkType()->getName() : '';
        return $subTypeName ?: $this->defaultTypeName;
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
     * @return Alaress_Date_Format
     */
    public function getPublishAt()
    {
        return $this->publishAt;
    }

    /**
     * @param Alaress_Date_Format $publishAt
     * @return $this
     */
    public function setPublishAt(Alaress_Date_Format $publishAt)
    {
        $this->publishAt = $publishAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getOpenAt()
    {
        return $this->openAt;
    }

    /**
     * @param Alaress_Date_Format $openAt
     * @return $this
     */
    public function setOpenAt(Alaress_Date_Format $openAt)
    {
        $this->openAt = $openAt;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getDueAt()
    {
        return $this->dueAt;
    }

    /**
     * @param Alaress_Date_Format $dueAt
     * @return $this
     */
    public function setDueAt(Alaress_Date_Format $dueAt)
    {
        $this->dueAt = $dueAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getCloseAt()
    {
        return $this->closeAt;
    }

    /**
     * @param Alaress_Date_Format $closeAt
     * @return $this
     */
    public function setCloseAt(Alaress_Date_Format $closeAt)
    {
        $this->closeAt = $closeAt;
        return $this;
    }

    /**
     * @return int
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
     * @return Collection|Participant[]
     */
    public function getAllParticipants()
    {
        return $this->allParticipants;
    }

    /**
     * @param Collection $allParticipants
     * @return $this
     */
    public function setAllParticipants(Collection $allParticipants)
    {
        $this->allParticipants = $allParticipants;
        return $this;
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
     * @return Collection|IResponse[]
     */
    public function getResponses()
    {
        return $this->responses;
    }

    /**
     * @param Collection|IResponse[] $responses
     * @return $this
     */
    public function setResponses(Collection $responses)
    {
        $this->responses = $responses;
        return $this;
    }

    /**
     * @return Collection|IFeedback[]
     */
    public function getFeedbacks()
    {
        return $this->feedbacks;
    }

    /**
     * @param Collection|IFeedback[] $feedbacks
     * @return $this
     */
    public function setFeedbacks(Collection $feedbacks)
    {
        $this->feedbacks = $feedbacks;
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
     * @return AssessmentContext|null
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
     * @return int
     */
    public function getMarkVisibility()
    {
        return $this->markVisibility;
    }

    /**
     * @param int $markVisibility
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
    public function setSpecificationAssessment(IAssessable $specification = null)
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
     * @param null|WorkType $workType
     * @return static
     */
    public function setWorkType(WorkType $workType = null)
    {
        $this->workType = $workType;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getCurriculumNodes()
    {
        return $this->curriculumNodes;
    }

    /**
     * @param Collection $curriculumNodes
     * @return $this
     */
    public function setCurriculumNodes(Collection $curriculumNodes)
    {
        $this->curriculumNodes = $curriculumNodes;
        return $this;
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
