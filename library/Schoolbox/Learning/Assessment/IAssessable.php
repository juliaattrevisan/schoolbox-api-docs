<?php
/**
 * IAssessable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use InvalidArgumentException;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use \Schoolbox\User;
use \Schoolbox\Collection;
use Alaress_Date_Format;
use \DomainException;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Curriculum\Node;

interface IAssessable extends ISoftDeletable
{
    public const MARK_VISIBILITY_PRIVATE = 0;
    public const MARK_VISIBILITY_STUDENT = 1;
    public const MARK_VISIBILITY_PARENT_STUDENT = 2;


    /**
     * @return string
     */
    public static function getDefaultTypeName(): string;


    /**
     * Returns this IAssessable if this IAssessable is not inside a project,
     * otherwise returns this IAssessable's root project.
     *
     * @return IAssessable
     */
    public function getBaseAssessable(): IAssessable;


    /**
     * Returns whether this IAssessable is a base assessable.
     *
     * (ie will return false if inside a project)
     *
     * @return bool
     */
    public function isBaseAssessable(): bool;


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
     * Is now after the published date?
     *
     * Optionally for a specific user.
     *
     * @param null|User                $student
     * @param Alaress_Date_Format|null $now
     * @return bool
     */
    public function isPublished(?User $student, Alaress_Date_Format $now = null): bool;


    /**
     * Is the assessment between its open and close dates (for a particular student)?
     *
     * Note this includes the overdue period.
     *
     * @param User                     $student
     * @param null|Alaress_Date_Format $now Optional value of now for testing
     * @return bool
     */
    public function isOpen(User $student, Alaress_Date_Format $now = null): bool;


    /**
     * Are we past the due date and before the close date?
     *
     * @param null|Alaress_Date_Format $now  Optional value of now for testing
     * @return bool
     */
    public function isOverdue(Alaress_Date_Format $now = null);


    /**
     * Are we after the close date? (does not consider if we are before open date)
     *
     * @param null|Alaress_Date_Format $now  Optional value of now for testing
     * @return bool
     */
    public function isPastClose(Alaress_Date_Format $now = null);


    /**
     * Can the specified learner (a user with READ perm only) create a response for themself ?
     *
     * In all cases, a learner may only create a response if they are a participant.
     *
     * @param User $learner A user with WRITE+ perm to this assessment's folder context
     * @return bool
     */
    public function canLearnerCreateResponse(User $learner);

    /**
     * Can the specified instructor (a user with WRITE+ perms) create a response for themself,
     * or a submission-on-behalf-of for another user ?
     *
     * @param User $instructor          A user with WRITE+ perm to this assessment's folder context
     * @param User $submittedOnBehalfOf The user who the response will be created for
     * @return bool
     */
    public function canInstructorCreateResponse(User $instructor, User $submittedOnBehalfOf);


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
     * Is this assessment a specification?
     *
     * A specification assessment exists within a unit specification.
     *
     * Currently an assessment may only be an implementation or specification, not
     * both or neither.
     *
     * @return bool
     */
    public function isSpecification();

    /**
     * Is this assessment an implementation?
     *
     * An implementation assessment exists within a folder or a unit implementation.
     *
     * Currently an assessment may only be an implementation or specification, not
     * both or neither.
     *
     * @return bool
     */
    public function isImplementation();

    /**
     * Gets the user role types which may view this assessment's marks and feedback.
     *
     * The user must still have permission to view the assessment for the given student.
     *
     * * self::MARK_VISIBILITY_PRIVATE: only staff may view
     * * self::MARK_VISIBILITY_STUDENT: staff and students may view
     * * self::MARK_VISIBILITY_PARENT_STUDENT: staff, students and parents may view
     *
     * @return int
     */
    public function getMarkVisibility();

    /**
     * Sets the mark visibility of this assessment.
     *
     * @param int $markVisibility
     * @return static
     */
    public function setMarkVisibility($markVisibility);

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
     * For Project, it'll just be "Project"
     * For other assessment types, it will be "Essay, Homework, etc"
     *
     * @return string
     */
    public function getTypeName();

    /**
     * Is this user a participant in this assessable?
     *
     * Excluded users are not participants.
     *
     * @param User $user
     * @return bool
     * @todo rename to isNonExcludedParticipant() for clarity
     */
    public function isParticipant(User $user);

    /**
     * Find a reference to a particular Participant in this Assessable
     * including excluded data and of deleted user
     * which saves you this traversal in Controllers etc
     *
     * @param User $user
     * @return Participant
     * @throws \UnexpectedValueException  If user is not a participant of this Assessment
     */
    public function getParticipant(User $user);

    /**
     * Find a reference to a particular Participant in this Assessable
     * which saves you this traversal in Controllers etc
     *
     * Returns null rather than throwing exception if $user is not a participant
     * including the case where the participant is excluded or the user is deleted
     *
     * @param User $user
     * @return Participant|null
     */
    public function getParticipantIfExtant(User $user);

    /**
     * Removes the specified user's participation from this IAssessment.
     *
     * @param User $user
     * @return bool TRUE if user was removed, FALSE if user was not found
     */
    public function unsetUserParticipation(User $user): bool;


    /**
     * Gets the normalised mark, regardless of MarkScheme
     *
     * @param Participant $participant
     * @param bool        $publishedOnly
     * @return float|null
     * @throws DomainException If participant assessment is not this assessment
     */
    public function getNormalisedMark(Participant $participant, $publishedOnly);


    /**
     * Gets the normalised mark, transformed into human-readable form by this assessment's MarkScheme
     *
     * @param Participant $participant
     * @param bool        $publishedOnly
     * @return string|null
     * @throws DomainException If participant assessment is not this assessment
     */
    public function getHumanReadableMark(Participant $participant, $publishedOnly);


    /**
     * Return the IResponse responsible for the participant's assessment mark
     *
     * In the case of a floating feedback without any response, then this returns null.
     *
     * @param Participant $participant
     * @param bool        $publishedOnly
     * @return null|IResponse
     * @throws DomainException If participant assessment is not this assessment
     */
    public function getAuthoritativeResponse(Participant $participant, $publishedOnly);


    /**
     * Get the IFeedback responsible for the participant's assessment mark
     *
     * Will return null if participant does not have a published mark
     *
     * @param Participant $participant
     * @param bool        $publishedOnly
     * @return null|IFeedback
     * @throws DomainException If participant assessment is not this assessment
     */
    public function getAuthoritativeFeedback(Participant $participant, $publishedOnly);

    /* Traversals
     */

    /**
     * Does the specified Participant need to submit a response?
     *
     * @param Participant $participant
     * @throws DomainException if participant's assessment is not this assessment
     * @return bool
     */
    public function isResponseRequired(Participant $participant);

    /**
     * Does the teacher need to give feedback to a student response?
     *
     * @param Participant $participant
     * @throws DomainException if participant's assessment is not this assessment
     * @return bool
     */
    public function isFeedbackRequired(Participant $participant);

    /**
     * Has the participant been marked as not-incomplete, and has not subsequently re-submitted ?
     *
     * @param Participant $participant
     * @throws DomainException if participant's assessment is not this assessment
     * @return bool
     */
    public function isReviewComplete(Participant $participant);


    /**
     * Has the participant made ANY kind of (submitted) response to the assessment?
     *
     * Includes teacher Submitting-On-Behalf-Of (S-O-B-O)
     *
     * Important: This will still return TRUE if task has been marked as 'incomplete'.
     *
     * @param Participant $participant
     * @throws DomainException if participant's assessment is not this assessment
     * @return bool
     */
    public function hasSubmittedResponse(Participant $participant);

    /**
     * Has a teacher given a published mark to the participant.
     *
     * For clarity - a self-assessed task, while complete, is not marked by teacher
     * @see $this->hasMark to include self-assessed marks.
     *
     * @param Participant $participant
     * @throws DomainException if participant's assessment is not this assessment
     * @return bool
     */
    public function hasPublishedFeedback(Participant $participant);


    /**
     * Returns whether the participant has any pending draft authoritative feedback.
     *
     * Note that this is not always simply ($anyMark === $publishedOnlyMark) as they may be the same.
     *
     * The implication of this is that the participant's mark may change if the draft is published.
     *
     * @param Participant $participant
     * @return bool
     * @throws DomainException If participant's assessment is not this assessment
     */
    public function hasPendingDraftAuthoritativeFeedback(Participant $participant): bool;


    /**
     * Does this student have a mark for the assessment.
     * (Will return TRUE even if just a comment is given)
     *
     * This includes marks given by the teacher, and
     * self-assessed marks for a task (even if self-assessed as 'Incomplete')
     *
     * @param Participant $participant
     * @throws DomainException if participant's assessment is not this assessment
     * @return bool
     */
    public function hasMark(Participant $participant);


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


    /**
     * Returns whether this assessment's feedback is visible to users of the specified role type
     *
     * @param string $roleType
     * @return bool
     */
    public function isFeedbackVisibleToRoleType(string $roleType): bool;


    /**
     * Returns the number of responses submitted to this assessment.
     *
     * (includes sub-assessment counts for projects)
     *
     * @param bool $includeDrafts
     * @return int
     */
    public function getCountResponses(bool $includeDrafts = false): int;


    /**
     * Returns the number of feedbacks given on this assessment
     *
     * (includes sub-assessment counts for projects)
     *
     * @param bool $includeDrafts
     * @return int
     */
    public function getCountFeedbacks(bool $includeDrafts = false): int;


    /**
     * Returns whether this assessment should only visible to staff and instructors
     *
     * @return bool
     */
    public function isStaffOrInstructorVisibleOnly(): bool;

    /* Getters/Setters
     */

    /**
     * @var int
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
     * Period in days to schedule an Assessment for during Course Import
     *
     * @return int
     */
    public function getDefaultOpenSpan();

    /**
     * Period in days to schedule an Assessment for during Course Import
     *
     * @param int $defaultOpenSpan
     * @return $this
     */
    public function setDefaultOpenSpan($defaultOpenSpan);

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
     * @return int
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
     * @return null|WorkType\Model
     */
    public function getWorkType();


    /**
     * @param null|WorkType\Model $workType
     * @return static
     */
    public function setWorkType(WorkType\Model $workType = null);

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
     * Get a Collection of Participant instances, indexed by user.id
     *
     * @return Collection|Participant[] (indexed by user.id)
     */
    public function getAllParticipants();


    /**
     * Get all users who are participating in this IAssessable, indexed by user.id
     *
     * @return User[]|GenericCollection Indexed by user.id
     */
    public function getParticipantUsers(): GenericCollection;


    /**
     * @return Plan[]|GenericCollection
     */
    public function getPlans(): GenericCollection;


    /**
     * @param Plan[]|GenericCollection $plans
     * @return $this
     */
    public function setPlans(GenericCollection $plans): self;


    /**
     * Returns this assessments dynamic plan if it has one.
     *
     * A dynamic plan is augmented with current class/group members from the assessment's folder context.
     *
     * @return null|Plan
     */
    public function getDynamicPlan(): ?Plan;


    /**
     * Returns whether this assessment has a dynamic plan
     *
     * @return bool
     */
    public function hasDynamicPlan(): bool;


    /**
     * Return the IResponse implementation class for this IAssessable
     *
     * @return string
     */
    public function getResponseClass();


    /**
     * Return the IFeedback implementation class for this IAssessable
     *
     * @return string
     */
    public function getFeedbackClass();


    /**
     * A collection of Curriculum Nodes
     *
     * @return Node\Model[]|GenericCollection
     */
    public function getCurriculumNodes();

    /**
     * Set a collection of Curriculum Nodes
     *
     * @param Node\Model[]|GenericCollection $curriculumNodes
     * @return $this
     */
    public function setCurriculumNodes(GenericCollection $curriculumNodes): self;

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


    /**
     * @return string|null
     */
    public function getExternalId(): ?string;


    /**
     * @param string|null $externalId
     * @return $this
     */
    public function setExternalId(?string $externalId): IAssessable;
}
