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

use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use \Schoolbox\Collection;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\MarkScheme\Completion;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use \Schoolbox\User;
use \Schoolbox\Mapper\Traits\Model\Id;
use \Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use \Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use \Schoolbox\Mapper\Traits\Model\DeletedAtDate;

abstract class AbstractAssessment implements IAssessable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var \Alaress_Date_Format
     */
    protected $publishAt;

    /**
     * @var \Alaress_Date_Format
     */
    protected $openAt;

    /**
     * @var \Alaress_Date_Format
     */
    protected $dueAt;

    /**
     * @var \Alaress_Date_Format
     */
    protected $closeAt;

    /**
     * @var float
     */
    protected $weight;

    /**
     * A Collection of Participant instances
     *
     * @var Collection|Participant[]
     */
    protected $allParticipants;

    /**
     * The person who created this Assessable
     *
     * @var User
     */
    protected $creator;

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
     * @var Collection|IFeedback
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
    private $specificationAssessment = null;

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
     * Assessable constructor.
     */
    public function __construct()
    {
        $this->setAllParticipants(new Collection());
        $this->responses = new Collection();
        $this->feedbacks = new Collection();
    }

    /**
     * George Cloney
     */
    public function __clone()
    {
        $this->setPublishAt($this->getPublishAt() instanceof \Alaress_Date_Format ? clone $this->getPublishAt() : null);
        $this->setOpenAt($this->getOpenAt() instanceof \Alaress_Date_Format ? clone $this->getOpenAt() : null);
        $this->setDueAt($this->getDueAt() instanceof \Alaress_Date_Format ? clone $this->getDueAt() : null);
        $this->setCloseAt($this->getCloseAt() instanceof \Alaress_Date_Format ? clone $this->getCloseAt() : null);

        /* Explicitly dump away all the Participants
         */
        $this->setAllParticipants(new Collection());

        /* TODO: Dump away all the attached files?
         */

        $this->setCreatedAt($this->getCreatedAt() instanceof \Alaress_Date_Format ? clone $this->getCreatedAt() : null);
        $this->setUpdatedAt($this->getUpdatedAt() instanceof \Alaress_Date_Format ? clone $this->getUpdatedAt() : null);
        $this->setDeletedAt($this->getDeletedAt() instanceof \Alaress_Date_Format ? clone $this->getDeletedAt() : null);
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
     * Find a reference to a particular Participant in this Assessable, if they exist.
     *
     * This was added for optimization reasons - it's far more performant to check if
     * a value is null than to throw and immediately catch an exception.
     *
     * @param User $user
     * @return Participant|null
     */
    protected function findParticipantIfExtant(User $user)
    {
        $userId = $user->getId();

        /** @var Participant $currentParticipant */
        foreach ($this->getAllParticipants() as $i => $currentParticipant) {
            if ($currentParticipant->getUser()->getId() == $userId) {
                $this->participantCache[$userId] = $i;
                return $currentParticipant;
            }
        }

        return null;
    }

    /**
     * Get the Human Readable Mark value for a particular User
     *
     * Optionally get the Published Human Readable Mark or the
     * currently-unpublished Human Readable Mark
     *
     * @param User $user
     * @param bool $publishedOnly
     * @return string|null
     */
    public function getHumanReadableMark(User $user, $publishedOnly)
    {
        $markScheme = $this->getMarkScheme();
        $response = null;

        // Special Case: Completion depends only presence of $response and/or $feedback
        if ($markScheme instanceof Completion) {
            $participant = $this->getParticipant($user);

            $feedback = $publishedOnly
                ? $participant->findMostRecentPublishedFeedback()
                : $participant->findMostRecentFeedback();

            if ($feedback) {
                $response = $feedback->getResponse();
            } else {
                $response = $participant->findMostRecentResponse();

                // Was this Assessment self-assessed ?
                if ($response && $response->getSubmittedBy()->getId() === $response->getSubmittedOnBehalfOf()->getId()) {
                    if($publishedOnly){
                        // Ignore any unpublished teacher feedback
                        $response = clone $response;
                        $response->setAllFeedback(new Collection());
                    }
                } else {
                    $response = null;
                }
            }
        }

        return $markScheme->getHumanReadableMark($this->getNormalisedMark($user, $publishedOnly), $response);
    }

    /**
     * Get the Normalised Mark value for a particular User
     *
     * Optionally get the Published Normalised Mark or the
     * currently-unpublished Normalised Mark
     *
     * @param User $user
     * @param bool $publishedOnly
     * @return float|null
     */
    public function getNormalisedMark(User $user, $publishedOnly)
    {
        return $this->getMark($user, $publishedOnly, true);
    }

    /**
     * Get the Mark for a particular User
     *
     * This is a macro method which can extract the normalised/human-readable
     * versions of the Mark. Also accounts for whether the Mark was in a Feedback
     * that was Published, or not.
     *
     * @param User $user
     * @param $publishedOnly
     * @param $normalised
     * @return float|null|string
     */
    protected function getMark(User $user, $publishedOnly, $normalised)
    {
        $participant = $this->getParticipant($user);

        $feedback = $publishedOnly
            ? $participant->findMostRecentPublishedFeedback()
            : $participant->findMostRecentFeedback();

        if($feedback){
            return $normalised
                ? $feedback->getNormalisedMark()
                : $feedback->getHumanReadableMark();
        }
        return null;
    }

    /* Traversals
     */

    /**
     * @inheritdoc
     *
     * @return int
     */
    public function numParticipants()
    {
        return $this->getAllParticipants()->count();
    }



    /**
     * Returns the number of Participants who have Responses to this Assessment
     *
     * @return int
     */
    public function numSubmitted()
    {
        $num = 0;
        foreach ($this->getAllParticipants() as $currentParticipant) {
            if (count($currentParticipant->getAllResponses())) {
                $num++;
            }
        }
        return $num;
    }

    /**
     * Returns what percentage of Participants have Responses to this Assessment
     *
     * @return float
     */
    public function getPercentageSubmitted()
    {
        $numParticipants = $this->numParticipants();
        if(!$numParticipants){
            return 0;
        }
        return 100 * ($this->numSubmitted() / $numParticipants);
    }

    /**
     * Returns the number of Participants who have neither Feedback nor Responses to this Assessment
     *
     * @return int
     */
    public function numNoResponseOrFeedback()
    {
        $num = 0;
        foreach ($this->getAllParticipants() as $currentParticipant) {
            if (!count($currentParticipant->getAllResponses()) && !count($currentParticipant->getAllFeedbacks())) {
                $num++;
            }
        }
        return $num;
    }

    /**
     * @inheritdoc
     *
     * @return int
     */
    public function numMarked()
    {
        $num = 0;
        foreach ($this->getAllParticipants() as $currentParticipant) {
            if ($currentParticipant->isAssessableMarked()) {
                $num++;
            }
        }
        return $num;
    }

    /**
     * @inheritdoc
     */
    public function getPercentageMarked()
    {
        $numParticipants = $this->numParticipants();
        if(!$numParticipants){
            return 0;
        }
        return 100 * ($this->numMarked() / $numParticipants);
    }

    /**
     * How many Participants have Responses that require marking.
     *
     * @return int
     */
    public function numMarkingRequired()
    {
        $num = 0;
        foreach ($this->getAllParticipants() as $participant) {
            if ($participant->isMarkingRequired()) {
                $num++;
            }
        }
        return $num;
    }

    /**
     * What percentage of Participants have Responses that require marking.
     *
     * This does not always equate to 100 - getPercentageMarked()
     *
     * @return float
     */
    public function getPercentageMarkingRequired()
    {
        $numParticipants = $this->numParticipants();
        if(!$numParticipants){
            return 0;
        }
        return 100 * ($this->numMarkingRequired() / $numParticipants);
    }

    /**
     * @inheritdoc
     */
    public function numPublished()
    {
        $num = 0;

        foreach ($this->getAllParticipants() as $currentParticipant) {
            if ($currentParticipant->isAssessablePublished()) {
                $num++;
            }
        }

        return $num;
    }

    /**
     * @inheritdoc
     */
    public function getPercentagePublished()
    {
        $numParticipants = $this->numParticipants();
        if(!$numParticipants){
            return 0;
        }
        return 100 * ($this->numPublished() / $numParticipants);
    }

    /* Validations
     */

    /**
     * Check that all four dates are set validly
     *
     * @return bool
     */
    public function isValidDates()
    {
        /* Null dates are invalid
         * Each date must be provided
         */
        if (!($this->getPublishAt() instanceof \Alaress_Date_Format) || ($this->getPublishAt() instanceof \Alaress_Date_NullDate)) {
            return false;
        }

        if (!($this->getOpenAt() instanceof \Alaress_Date_Format) || ($this->getPublishAt() instanceof \Alaress_Date_NullDate)) {
            return false;
        }

        if (!($this->getDueAt() instanceof \Alaress_Date_Format) || ($this->getPublishAt() instanceof \Alaress_Date_NullDate)) {
            return false;
        }

        if (!($this->getCloseAt() instanceof \Alaress_Date_Format) || ($this->getPublishAt() instanceof \Alaress_Date_NullDate)) {
            return false;
        }

        /* Check publish date against everything else
         */
        if ($this->getPublishAt()->gt($this->getOpenAt()) || $this->getPublishAt()->gt($this->getDueAt()) || $this->getPublishAt()->gt($this->getCloseAt())) {
            return false;
        }

        /* Check open date against everything else
         */
        if ($this->getOpenAt()->gt($this->getDueAt()) || $this->getOpenAt()->gt($this->getCloseAt())) {
            return false;
        }

        /* Check due date against everything else
         */
        if ($this->getDueAt()->gt($this->getCloseAt())) {
            return false;
        }

        return true;
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
     * @return \Alaress_Date_Format
     */
    public function getPublishAt()
    {
        return $this->publishAt;
    }

    /**
     * @param \Alaress_Date_Format $publishAt
     * @return $this
     */
    public function setPublishAt($publishAt)
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
     * @param \Alaress_Date_Format $openAt
     * @return $this
     */
    public function setOpenAt($openAt)
    {
        $this->openAt = $openAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getDueAt()
    {
        return $this->dueAt;
    }

    /**
     * @param \Alaress_Date_Format $dueAt
     * @return $this
     */
    public function setDueAt($dueAt)
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
     * @param \Alaress_Date_Format $closeAt
     * @return $this
     */
    public function setCloseAt($closeAt)
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
}
