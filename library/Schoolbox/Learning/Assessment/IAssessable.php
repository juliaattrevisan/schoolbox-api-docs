<?php
/**
 * IAssessable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;

use InvalidArgumentException;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use \Schoolbox\User;
use \Schoolbox\Collection;

interface IAssessable extends ISoftDeletable
{
    const MARK_VISIBILITY_PRIVATE = 0;
    const MARK_VISIBILITY_PARENT_STUDENT = 2;
    const MARK_VISIBILITY_STUDENT = 1;

    /**
     * Creates a clone of this assessment.
     *
     * This clone must be able to be persisted to create a copy of this assessment,
     * without further modification.
     *
     * @return static
     */
    public function cloneForCopy();

    /**
     * Return the context within which this assessment belongs.
     *
     * Currently, they may belong to:
     * * a folder
     * * a unit specification
     * * a unit implementation
     * * a project
     *
     * @return AssessmentContext
     */
    public function getContext();

    /**
     * Return the ultimate context within which this assessment belongs.
     *
     * Currently, they may belong to:
     * * a folder
     * * a unit specification
     * * a unit implementation
     *
     * @return AssessmentContext
     */
    public function getBaseContext();

    /**
     * Set the context within which this assessment belongs.
     *
     * @param AssessmentContext $assessmentContext
     * @return static
     */
    public function setContext(AssessmentContext $assessmentContext);

    /**
     * Decide whether $user can create a response to this IAssessable
     *
     * @param User $user
     * @return bool
     */
    public function canCreateResponse(User $user);

    /**
     * Retrieve the specification this assessment was created from, if any.
     *
     * When an assessment is created as part of creating a new unit implementation,
     * it will contain a reference back to the specification it was cloned from.
     *
     * @return IAssessable|null
     */
    public function getSpecificationAssessment();

    /**
     * Set the specification this assessment was created from, if any.
     *
     * @param IAssessable|null $specification
     * @return static
     */
    public function setSpecificationAssessment(IAssessable $specification = null);

    /**
     * Returns a readable family name for this object.
     *
     * This will be the same for all objects of the same type.
     *
     * @return string
     */
    public function getFamilyName();

    /**
     * Return the name of the "type" of Assessable this is
     *
     * For Due Works, it'll be "Essay, Homework, etc"
     * For Quiz, it'll just be "Quiz"
     * For Task, it'll just be "Task"
     * For Project, it'll just be "Project"
     *
     * @return mixed
     */
    public function getTypeName();

    /**
     * Is this user a participant in this assessable?
     *
     * @param User $user
     * @return bool
     */
    public function isParticipant(User $user);

    /**
     * Find a reference to a particular Participant in this Assessable
     * which saves you this traversal in Controllers etc
     *
     * @param User $user
     * @return Participant
     * @throws \UnexpectedValueException
     */
    public function getParticipant(User $user);

    /**
     * @param User $user
     * @param bool $publishedOnly
     * @return string|null
     */
    public function getHumanReadableMark(User $user, $publishedOnly);

    /**
     * @param User $user
     * @param bool $publishedOnly
     * @return float|null
     */
    public function getNormalisedMark(User $user, $publishedOnly);

    /* Traversals
     */

    /**
     * Calculate how many Participant instances are in this IAssessable
     *
     * @return int
     */
    public function numParticipants();

    /**
     * Returns what percentage of Participants have Responses to this Assessment
     *
     * @return float
     */
    public function getPercentageSubmitted();

    /**
     * Returns the number of Participants who have neither Feedback nor Responses to this Assessment
     *
     * @return int
     */
    public function numNoResponseOrFeedback();

    /**
     * Returns the number of Participants who have Responses to this Assessment
     *
     * @return int
     */
    public function numSubmitted();

    /**
     * Calculate how many IAssessable instances have a non-null mark value
     *
     * The most recent action determines the marked/not-marked state of
     * an Assessable. This accounts for a series of edge cases which really
     * make you hate life
     *
     * For a particular Participant:
     *
     * If the Assessable contains zero Responses and zero Feedbacks:
     *     >>> not marked
     *
     * If the Assessable contains at least one Response and zero Feedbacks:
     *     >>> not marked
     *
     * If the Assessable contains zero Responses and at least one Feedback:
     *     >>> marked
     *
     * If the Assessable contains at least one Response and at least one Feedback:
     * Start traversing to find out what's going on.
     *          If the most recent Response contains a Feedback -OR-
     *          If a Feedback occurred more recently than the Response
     *          (ie. Feedback is the most recent action) >>> marked
     *
     *          Otherwise >>> not marked
     */
    public function numMarked();

    /**
     * Calculate percentage of an IAssessable that has been marked
     *
     * @return float
     */
    public function getPercentageMarked();

    /**
     * How many Participants have Responses that require marking.
     *
     * @return int
     */
    public function numMarkingRequired();

    /**
     * What percentage of Participants have Responses that require marking.
     *
     * This does not always equate to 100 - getPercentageMarked()
     *
     * @return float
     */
    public function getPercentageMarkingRequired();

    /**
     * Calculate the number of Feedbacks that have been Published
     * to Students
     *
     * @return int
     */
    public function numPublished();

    /**
     * Calculate percentage of an IAssessable that has been Published
     *
     * @return float
     */
    public function getPercentagePublished();

    /**
     * The friendly, uninformative version of assertValidDates
     *
     * @return bool
     */
    public function isValidDates();

    /**
     * Check that all four dates are set validly
     *
     * @throws InvalidArgumentException if the dates are not validly set
     */
    public function assertValidDates();

    /**
     * Is feedback on this Assessment visible to this user?
     *
     * @param User $viewingUser
     * @return bool
     */
    public function isFeedbackVisibleToUser(User $viewingUser);

    /* Getters/Setters
     */

    /**
     * @var string
     */
    public function getId();

    /**
     * @param  int $id
     *
     * @return $this
     */
    public function setId($id);

    /**
     * Get the title of the IAssessable
     *
     * @return string
     */
    public function getTitle();

    /**
     * Set the title of the IAssessable
     *
     * @param string $title
     * @return $this
     */
    public function setTitle($title);

    /**
     * Get the description of the IAssessable
     *
     * This is typically the instructions to the participant
     * as to what the IAssessable is about etc.
     *
     * @return string
     */
    public function getDescription();

    /**
     * Set the description of the IAssessable
     *
     * @param string $description
     * @return $this
     */
    public function setDescription($description);

    /**
     * Get the date when this IAssessable was made accessible to respondents
     * (typically students)
     *
     * This is the date at which the IAssessable becomes both visible
     * and available for user participation
     *
     * A value of null or instance of Alaress_Date_NullDate indicates that
     * this IFeedback was never published
     *
     * @return \Alaress_Date_Format
     */
    public function getPublishAt();

    /**
     * Set the date when this IAssessable was made accessible to respondents
     *
     * @param \Alaress_Date_Format $publishAt
     * @return $this
     */
    public function setPublishAt(\Alaress_Date_Format $publishAt);

    /**
     * Get the date when this IAssessable opens
     *
     * This is the date at which participants can create Responses to this IAssessable
     *
     * A value of null or instance of Alaress_Date_NullDate indicates that
     * this IAssessable will never open
     *
     * @return \Alaress_Date_Format
     */
    public function getOpenAt();

    /**
     * Set the date when this IAssessable opens
     *
     * @param \Alaress_Date_Format $openAt
     * @return $this
     */
    public function setOpenAt(\Alaress_Date_Format $openAt);

    /**
     * Get the date when this IAssessable is due
     *
     * This is the date by which participants should create their Responses to this IAssessable
     *
     * A value of null or instance of Alaress_Date_NullDate indicates that
     * this IAssessable has no particular date to check against (it will be
     * subclass-specific logic which uses it)
     *
     * @return \Alaress_Date_Format
     */
    public function getDueAt();

    /**
     * Set the date when this IAssessable is due
     *
     * @param \Alaress_Date_Format $dueAt
     * @return $this
     */
    public function setDueAt(\Alaress_Date_Format $dueAt);

    /**
     * Get the date when this IAssessable closes
     *
     * When this date is reached, any IResponses spawned are indicated as being "late"
     *
     * A value of null or instance of Alaress_Date_NullDate indicates that
     * this IAssessable has no particular date to check against (it will be
     * subclass-specific logic which uses it)
     *
     * @return \Alaress_Date_Format
     */
    public function getCloseAt();

    /**
     * Set the date when this IAssessable closes
     *
     * @param \Alaress_Date_Format $closeAt
     * @return $this
     */
    public function setCloseAt(\Alaress_Date_Format $closeAt);

    /**
     * Get the weight
     *
     * A multiplier applied to a Normalised Mark
     *
     * A weight function is a mathematical device used when performing a sum, integral,
     * or average to give some elements more "weight" or influence on the result than
     * other elements in the same set.
     *      -- https://en.wikipedia.org/wiki/Weight_function
     *
     * @return float
     */
    public function getWeight();

    /**
     * Set the weight
     *
     * @param float $weight
     * @return $this
     */
    public function setWeight($weight);

    /**
     * @return MarkType
     */
    public function getMarkType();

    /**
     * @param MarkType $markType
     * @return static
     */
    public function setMarkType(MarkType $markType);

    /**
     * @return int
     */
    public function getSequence();

    /**
     * @param int $sequence
     * @return static
     */
    public function setSequence($sequence);

    /**
     * @return GradeGroup|null
     */
    public function getGradeGroup();

    /**
     * Must allow nulls (eg for Tasks and Quiz, which for the moment cannot have LetterGrade MarkTypes)
     *
     * @param GradeGroup|null $gradeGroup
     * @return static
     */
    public function setGradeGroup(GradeGroup $gradeGroup);

    /**
     * @return IMarkScheme
     */
    public function getMarkScheme();

    /**
     * @param IMarkScheme $markScheme
     * @return static
     */
    public function setMarkScheme(IMarkScheme $markScheme);

    /**
     * Get the person who created this IAssessable
     *
     * @return User
     */
    public function getCreator();

    /**
     * Set the person who created this IAssessable
     *
     * @param User $creator
     * @return $this
     */
    public function setCreator($creator);

    /**
     * Get a Collection of Participant instances
     *
     * @return Collection|Participant[]
     */
    public function getAllParticipants();

    /**
     * Set a Collection of Participant instances
     *
     * @param Collection|Participant[] $allParticipants
     * @return $this
     */
    public function setAllParticipants(Collection $allParticipants);

    /**
     * @return Collection|IResponse[]
     */
    public function getResponses();

    /**
     * @param Collection|IResponse[] $responses
     * @return $this
     */
    public function setResponses(Collection $responses);

    /**
     * @return Collection|IFeedback[]
     */
    public function getFeedbacks();

    /**
     * @param Collection|IFeedback[] $feedbacks
     * @return $this
     */
    public function setFeedbacks(Collection $feedbacks);

    /**
     * Created date
     *
     * @return \Alaress_Date_Format
     */
    public function getCreatedAt();

    /**
     * @param  \Alaress_Date_Format $createdAt
     *
     * @return $this
     */
    public function setCreatedAt(\Alaress_Date_Format $createdAt = null);

    /**
     * @return \Alaress_Date_Format
     */
    public function getUpdatedAt();

    /**
     * @param  \Alaress_Date_Format $updatedAt
     *
     * @return $this
     */
    public function setUpdatedAt(\Alaress_Date_Format $updatedAt = null);

    /**
     * @return \Alaress_Date_Format
     */
    public function getDeletedAt();

    /**
     * @param  \Alaress_Date_Format $deletedAt
     * @return $this
     */
    public function setDeletedAt(\Alaress_Date_Format $deletedAt = null);
}
