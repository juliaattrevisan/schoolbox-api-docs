<?php
/**
 * Project
 *
 * Composite Design Pattern
 *
 * This contains a Collection of IAssessables such as Due Work,
 * Quiz and Tasks.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/* TODO: Move into Learning/Assessment/Project/Model
 */
namespace Schoolbox\Learning\Assessment;

use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use \Schoolbox\User;
use \Schoolbox\Learning\Assessment\Summator\ISummator;
use Schoolbox\Resource\Folder\Model as Folder;

class Project extends AbstractAssessment implements AssessmentContext
{

    /**
     * @var GenericCollection|IAssessable[]
     */
    protected $assessables;

    /**
     * Summation strategy
     *
     * @var ISummator
     */
    protected $summator;

    /**
     * Number of decimal places used in a Normalised Mark value
     *
     * @var int
     */
    protected $precision = 11;


    /**
     * @return string
     */
    public static function getDefaultTypeName(): string
    {
        return 'Project';
    }


    /**
     * Project constructor.
     *
     * @param ISummator $summator
     */
    public function __construct(ISummator $summator = null)
    {
        /* I know Dr hates hook methods, so do this instead
         */
        parent::__construct();

        $this->assessables = new GenericCollection(IAssessable::class);
        $this->setSummator($summator);
    }

    /**
     * This project is a specification if the context it's within is one.
     *
     * @return bool
     */
    public function isSpecificationContext()
    {
        return $this->getContext()->isSpecificationContext();
    }

    /**
     * This project is an implementation if the context it's within is one.
     *
     * @return bool
     */
    public function isImplementationContext()
    {
        return $this->getContext()->isImplementationContext();
    }

    /**
     * Does this have assessments at all?
     *
     * @return bool
     */
    public function hasAssessments()
    {
        return count($this->assessables) > 0;
    }

    /**
     * @inheritdoc
     */
    public function canLearnerCreateResponse(User $learner)
    {
        // Project containers cannot have IResponses directly on them. Only their sub-assessments can.
        return false;
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
     * Calculate the final mark for this IAssessable, for a particular User
     *
     * Projects use Summator strategies to determine final marks
     * for a Collection of Assessable instances
     *
     * @inheritDoc
     */
    public function getNormalisedMark(Participant $participant, $publishedOnly)
    {
        $this->assertSameAssessment($participant);
        return $this->getSummator()->summate($this->assessables, $participant->getUser(), $publishedOnly);
    }


    /**
     * Whether a project requires a student response depends on:
     * * the summation method, and
     * * the responseRequired status of the project's sub-assessments
     *
     * If summation implies ALL, then if ANY sub-assessment requires a response, the project requires a response
     * If summation implies ANY, then if ANY sub-assessment not not require a response, the project does not require a response.
     *
     * @inheritdoc
     */
    public function isResponseRequired(Participant $participant)
    {
        // Would a single sub-response indicate the student has responded ?
        $canCompleteAny  = $this->summator->impliesOptionality();
        $mustCompleteAll = !$canCompleteAny;

        foreach ($this->iterateAssessablesForParticipant($participant) as $subParticipant) {
            $subResponseRequired = $subParticipant->isResponseRequired();

            // Does the project require all sub-assessments to be responded to, and this sub-assessment requires a response?
            if ($mustCompleteAll && $subResponseRequired) {
                // Project requires a response
                return true;
            }
            // Else does the project require any sub-assessment to be responded to, and this sub-assessment does not require a response?
            if ($canCompleteAny && !$subResponseRequired) {
                // Project does not require a response
                return false;
            }
        }

        /* We did not return during loop, so we can conclude that either:
         * * Can respond to any, but none found that were responded (so student needs to respond), or
         * * Must respond to all, and all were responded to (so student does not need to respond).
         */
        return $canCompleteAny;
    }


    /**
     * Resubmission is required on a project if resubmission is required on any
     * of its sub-assessments.
     *
     * @inheritdoc
     */
    public function isResubmissionRequired(Participant $participant): bool
    {
        foreach ($this->iterateAssessablesForParticipant($participant) as $subParticipant) {
            if ($subParticipant->isResubmissionRequired()) {
                return true;
            }
        }

        return false;
    }


    /**
     * If a student requires marking on ANY project sub-assessment, the project requires marking (Orange Diamond)
     *
     * @inheritdoc
     * @throws \DomainException if user is not found in any project sub-assessments
     */
    public function isFeedbackRequired(Participant $participant)
    {
        foreach ($this->iterateAssessablesForParticipant($participant) as $subParticipant) {
            if ($subParticipant->isFeedbackRequired()) {
                return true;
            }
        }

        return false;
    }


    /**
     * Whether a project can be considered reviewComplete (ie GREEN CIRCLE) depends on:
     * * the summation method, and
     * * the reviewComplete status of the project's sub-assessments, and
     * * ALL sub-assessments not requiring feedback
     *
     * We need to examine all sub-assessments before we can conclude the project's overall state
     *
     * If any sub-assessment requires feedback, the project is NOT review-completed
     * If summation implies ALL and any sub-assessment is not review-complete, then project is not review-complete
     * If summation implies ANY and any sub-assessment is review-complete, then project is review-complete
     *
     * @inheritdoc
     */
    public function isReviewComplete(Participant $participant)
    {
        // Would a single sub-response indicate the student has responded ?
        $canCompleteAny         = $this->summator->impliesOptionality();
        $mustCompleteAll        = !$canCompleteAny;
        $subReviewCompleteFound = false;
        foreach ($this->iterateAssessablesForParticipant($participant) as $subParticipant) {
            $subFeedbackRequired = $subParticipant->isFeedbackRequired();
            $subReviewComplete   = $subParticipant->isReviewComplete();

            // Does sub-assessment require feedback ? Or does project require all to be review-complete and the sub-assessment in not review-complete?
            if ($subFeedbackRequired || ($mustCompleteAll && !$subReviewComplete)) {
                // Conclude that project is not review-complete
                return false;
            }

            if ($subReviewComplete) {
                $subReviewCompleteFound = true;
            }
        }

        /* We did not return during loop, so we can conclude that either:
         * * Can complete any, and we may or may not have found a subAssessment that is review-complete
         * * Must complete all, and no subAssessments found that were not review-completed
         */
        return $subReviewCompleteFound;
    }


    /**
     * This treats the project holistically as an atomic entity.
     *
     * If a project requires ALL sub-assessments to be completed, then student has only
     * submitted a response to the project once they have submitted to ALL sub-assessments.
     *
     * Else if a project requires any sub-assessment to be completed, then student has submitted once
     * they have submitted a response to ANY sub-assessment.
     *
     * @inheritdoc
     */
    public function hasSubmittedResponse(Participant $participant)
    {
        // Would a single sub-assessment completion imply the project is complete ?
        $canResponseToAny = $this->summator->impliesOptionality();
        $mustRespondToAll = !$canResponseToAny;
        foreach ($this->iterateAssessablesForParticipant($participant) as $subParticipant) {
            $hasSubmittedSubResponse = $subParticipant->hasSubmittedResponse();

            // Does project require all sub-assessment to be completed, and student didn't respond ?
            if ($mustRespondToAll && !$hasSubmittedSubResponse) {
                // We can conclude they have not responded to the project
                return false;
            }
            // Else does project require any, and student has responded ?
            if ($canResponseToAny && $hasSubmittedSubResponse) {
                // We can conclude they have responded to the project
                return true;
            }
        }

        /* Since we did not return early, we can conclude either:
         * * Can respond to any, but none found (student has not responded to project), or
         * * Must respond to all, and none found that were not responded (student has responded to project)
         */
        return $mustRespondToAll;
    }


    /**
     * This treats the project holistically as an atomic entity.
     *
     * If a project requires ALL sub-assessments to be completed, then teacher has only
     * given feedback to the project once they have given feedback to ALL sub-assessments.
     *
     * Else if a project requires any sub-assessment to be completed, then teacher has given feedback once
     * they have given feedback to ANY sub-assessment.
     *
     * @inheritdoc
     */
    public function hasPublishedFeedback(Participant $participant)
    {
        // Would a single sub-assessment completion imply the project is complete ?
        $canGiveFeedbackToAny  = $this->summator->impliesOptionality();
        $mustGiveFeedbackToAll = !$canGiveFeedbackToAny;

        foreach ($this->iterateAssessablesForParticipant($participant) as $subParticipant) {
            $hasPublishedSubFeedback = $subParticipant->hasPublishedFeedback();

            // Does project require all sub-assessment to be completed, and student hasn't received published feedback ?
            if ($mustGiveFeedbackToAll && !$hasPublishedSubFeedback) {
                // We can conclude they have not received published feedback for the project
                return false;
            }
            // Else does project require any, and student has received published feedback for a sub-assessment ?
            if ($canGiveFeedbackToAny && $hasPublishedSubFeedback) {
                // We can conclude they have received published feedback to the project
                return true;
            }
        }

        /* Since we did not return early, we can conclude either:
         * * Can give feedback to any, but none found (student has not received feedback to project), or
         * * Must give feedback to all, and none found that did not have feedback (student has feedback for project)
         */
        return $mustGiveFeedbackToAll;
    }


    /**
     * @inheritdoc
     */
    public function hasPendingDraftAuthoritativeFeedback(Participant $participant): bool
    {
        // A project participant has pending draft feedback if any sub-assessable has pending draft feedback.
        foreach ($this->iterateAssessablesForParticipant($participant) as $subParticipant) {
            $subAssessable = $subParticipant->getAssessable();
            if ($subParticipant && $subAssessable->hasPendingDraftAuthoritativeFeedback($subParticipant)) {
                return true;
            }
        }
        return false;
    }


    /**
     * This does NOT treat the project holistically as an atomic entity.
     *
     * A project has a mark if any of its sub-assessments have marks.
     *
     * @inheritdoc
     */
    public function hasMark(Participant $participant)
    {
        foreach ($this->iterateAssessablesForParticipant($participant) as $subParticipant) {
            // Does student have a mark for the sub-assessment ?
            if ($subParticipant->hasMark()) {
                return true;
            }
        }
        return false;
    }


    /**
     * @inheritdoc
     */
    public function getAuthoritativeResponse(Participant $participant, $publishedOnly)
    {
        // Project do not have responses
        return null;
    }


    /**
     * @inheritdoc
     */
    public function getAuthoritativeFeedback(Participant $participant, $publishedOnly)
    {
        // Project do not have feedback
        return null;
    }


    /**
     * Check the dates set on this model are valid
     * @throws InvalidArgumentException if the dates are not validly set
     * @return void
     */
    public function assertValidDates()
    {
        /* Don't check dates if in course */
        if ($this->getBaseContext() instanceof UnitSpecification) {
            return;
        }

        $this->assertDateSequence([
            'publish' => $this->getPublishAt(),
            'due'     => $this->getDueAt()
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getFamilyName()
    {
        return 'project';
    }

    /**
     * Override: open is always aliased to publish
     *
     * @return \Alaress_Date_Format
     */
    public function getOpenAt()
    {
        return $this->getPublishAt();
    }

    /**
     * Override: close is always aliased to due
     *
     * @return \Alaress_Date_Format
     */
    public function getCloseAt()
    {
        return $this->getDueAt();
    }

    /**
     * A project mark is visible if all sub-assessment marks are visible.
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
        // Does user lack visibility to any sub-assessment's feedback ?
        foreach ($this->assessables as $subAssessable) {
            if (!$subAssessable->isFeedbackVisibleToRoleType($roleType)) {
                return false;
            }
        }

        return true;
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
        foreach ($this->assessables as $assessable) {
            $count += $assessable->getCountResponses($includeDrafts);
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
        foreach ($this->assessables as $assessable) {
            $count += $assessable->getCountFeedbacks($includeDrafts);
        }
        return $count;
    }


    /**
     * Clones this object in preparation for persisting as a copy of this object.
     *
     * @return static
     */
    public function cloneForCopy()
    {
        /** @var Project $clone */
        $clone = parent::cloneForCopy();
        $clone->assessables = new GenericCollection(IAssessable::class);
        return $clone;
    }


    /**
     * @inheritdoc
     */
    public function unsetUserParticipation(User $user): bool
    {
        // Remove from this project.
        $userRemoved = parent::unsetUserParticipation($user);

        // And remove from sub-assessables.
        foreach ($this->assessables as $subAssessable) {
            if ($subAssessable->unsetUserParticipation($user)) {
                $userRemoved = true;
            }
        }
        return $userRemoved;
    }

    /* Getters/Setters
     */

    /**
     * @return GenericCollection|IAssessable[]
     */
    public function getAssessables()
    {
        return $this->assessables;
    }

    /**
     * @param Collection|IAssessable[] $assessables
     * @return self
     */
    public function setAssessables(Collection $assessables)
    {
        $this->assessables->replaceWith($assessables);
        return $this;
    }

    /**
     * @return ISummator
     */
    public function getSummator()
    {
        return $this->summator;
    }

    /**
     * @param ISummator $summator
     * @return $this
     */
    public function setSummator(ISummator $summator = null)
    {
        $this->summator = $summator;
        return $this;
    }

    /**
     * @return int
     */
    public function getPrecision()
    {
        return $this->precision;
    }


    /**
     * Retrieves the folder this Project ultimately belongs to.
     *
     * @return Folder
     */
    public function getFolder()
    {
        return $this->getContext()->getFolder();
    }

    /**
     * Retrieves the parent context of this project
     *
     * @return AssessmentContext
     */
    public function getBaseContext()
    {
        return $this->getContext();
    }


    /**
     * Project-specific method to get all users who are participating in this project and its sub-assessments
     *
     * @return User[]|GenericCollection Indexed by user.id
     * @throws \UnexpectedValueException If unable to merge collections
     */
    public function getParticipantUsers(): GenericCollection
    {
        // Base case
        $participatingUsers = parent::getParticipantUsers();

        // Recursive case
        foreach($this->assessables as $subAssessable){
            $participatingUsers = $participatingUsers->mergeUnique($subAssessable->getParticipantUsers(), 'id');
        }
        return $participatingUsers;
    }


    /**
     * Return an iterator over the set of assessables in which `$participant` is
     * actually participating.
     *
     * @param Participant $participant
     * @return iterable&Participant[] The iterator yields the Participant record
     *                                for each sub-assessment in which
     *                                `$participant` is participating
     * @throws \DomainException       If `$participant`'s assessment is not this
     *                                project
     * @throws \DomainException       If there's at least one assessment in this
     *                                project, and `$participant`'s user is not
     *                                participating in any assessment in this
     *                                project
     */
    private function iterateAssessablesForParticipant(Participant $participant): iterable
    {
        $this->assertSameAssessment($participant);

        if (!$this->hasAssessments()) {
            return;
        }

        $studentFoundInSubAssessments = false;
        foreach ($this->assessables as $subAssessment) {
            $subParticipant = $subAssessment->getParticipantIfExtant($participant->getUser());
            if ($subParticipant) {
                $studentFoundInSubAssessments = true;
                yield $subParticipant;
            }
        }

        // Logically, if student is a participant in a project,
        // they must be a participant in at least 1 project sub-assessment
        if (!$studentFoundInSubAssessments) {
            throw new \DomainException(
                sprintf(
                    "Project (a.id: %d) participant (u.id: %d}) not found in project's sub-assessments",
                    $this->id,
                    $participant->getUser()->getId()
                )
            );
        }
    }
}
