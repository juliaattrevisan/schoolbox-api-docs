<?php
/**
 * Participant
 *
 * A "flattened" version of the Submission/Completion/Attempt--Feedback
 * hierarchy that currently exists, so you don't need to traverse so much
 * to find particular Feedbacks for a particular User
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Feedback\InstructorGivenFilter;
use Schoolbox\Learning\Assessment\Feedback\PublishedFilter;
use Schoolbox\Learning\Assessment\Feedback\SelfAssessmentFilter;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use \Schoolbox\User;
use \DomainException;
use Alaress_Date_Format;

/**
 * The 'domain' version of a participant
 */
class Participant
{

    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;

    /**
     * The User to whom all these Responses and Feedbacks are relevant
     *
     * @var User
     */
    private $user;

    /**
     * The Plan this participant is in.
     *
     * @var Plan
     */
    private $plan;


    /**
     * @var null|Alaress_Date_Format
     */
    private $excludedAt;


    /**
     * A Collection of IResponse instances that Students spawn
     *
     * @var GenericCollection|IResponse[]
     */
    private $allResponses;

    /**
     * A Collection of IFeedback instances that Teachers spawn
     *
     * @var GenericCollection|IFeedback[]
     */
    private $allFeedbacks;


    /**
     * Participant constructor.
     *
     * @param User $user
     * @param Plan $plan
     */
    public function __construct(User $user, Plan $plan)
    {
        $this->user         = $user;
        $this->plan         = $plan;
        $this->allResponses = new GenericCollection($plan->getAssessable()->getResponseClass());
        $this->allFeedbacks = new GenericCollection($plan->getAssessable()->getFeedbackClass());
    }


    /**
     * Does the student still need to submit a response ?
     *
     * No need if already responded and the response is not 'incomplete', or
     * if marked by Teacher already as not 'incomplete'
     *
     * Represented as GREY SQUARE
     *
     * @return bool
     */
    public function isResponseRequired()
    {
        try {
            return $this->plan->getAssessable()->isResponseRequired($this);
        } catch (DomainException $e) {
            // Impossible to throw when calling $this->assessable($this)
            trigger_error($e->getMessage(), E_USER_WARNING);
            return null;
        }
    }


    /**
     * Does the teacher need to mark this participant ?
     *
     * Represented in UI as ORANGE DIAMOND
     *
     * This is a teacher-perspective function. ie, does the teacher need to do anything?
     * eg, Give a mark? Publish a draft? Mark off as 'complete' ?
     *
     * @return bool
     */
    public function isFeedbackRequired()
    {
        try {
            return $this->plan->getAssessable()->isFeedbackRequired($this);
        } catch (DomainException $e) {
            // Impossible to throw when calling $this->assessable($this)
            trigger_error($e->getMessage(), E_USER_WARNING);
            return null;
        }
    }


    /**
     * Does the learner need to resubmit feedback for this participation?
     *
     * Represented in UI as RED CIRCLE w/ CROSS
     *
     * @return bool
     */
    public function isResubmissionRequired(): ?bool
    {
        try {
            return $this->plan->getAssessable()->isResubmissionRequired($this);
        } catch (DomainException $e) {
            // Impossible to throw when calling $this->assessable($this)
            trigger_error($e->getMessage(), E_USER_WARNING);
            return null;
        }
    }


    /**
     * Has the teacher marked this participant as not-explicitly-incomplete, and
     * the student has not subsequently submitted/re-submitted.
     *
     * Represented in UI as GREEN CIRCLE
     */
    public function isReviewComplete()
    {
        try {
            return $this->plan->getAssessable()->isReviewComplete($this);
        } catch (DomainException $e) {
            // Impossible to throw when calling $this->assessable($this)
            trigger_error($e->getMessage(), E_USER_WARNING);
            return null;
        }
    }


    /**
     * Has this participant made ANY kind of (submitted) response to this assessment?
     *
     * Includes teacher Submitting-On-Behalf-Of (S-O-B-O)
     *
     * Important: This will still return TRUE if task has been marked as 'incomplete'.
     *
     * @return bool
     */
    public function hasSubmittedResponse()
    {
        try {
            return $this->plan->getAssessable()->hasSubmittedResponse($this);
        } catch (DomainException $e) {
            // Impossible to throw when calling $this->assessable($this)
            trigger_error($e->getMessage(), E_USER_WARNING);
            return null;
        }
    }


    /**
     * Has a teacher given a published mark to this participant.
     *
     * For clarity - a self-assessed task, while complete, is not marked by teacher
     *
     * @see $this->hasMark to include self-assessed marks.
     *
     * @return bool
     */
    public function hasPublishedFeedback()
    {
        try {
            return $this->plan->getAssessable()->hasPublishedFeedback($this);
        } catch (DomainException $e) {
            // Impossible to throw when calling $this->assessable($this)
            trigger_error($e->getMessage(), E_USER_WARNING);
            return null;
        }
    }


    /**
     * Does this student have a mark for the assessment.
     *
     * This includes marks given by the teacher, and
     * self-assessed marks for a task (even if self-assessed as 'Incomplete')
     *
     * @see $this->isMarkedByTeacher for teacher-assigned-only marks
     * @throws DomainException
     * @return bool
     */
    public function hasMark()
    {
        return $this->plan->getAssessable()->hasMark($this);
    }


    /**
     * @return IResponse|null
     */
    public function findMostRecentResponse()
    {
        /** @var IResponse $mostRecentResponse */
        $mostRecentResponse = null;

        /** @var IResponse $response */
        foreach ($this->allResponses as $response) {
            if (!$mostRecentResponse || $response->getUpdatedAt()->gt($mostRecentResponse->getUpdatedAt())) {
                $mostRecentResponse = $response;
            }
        }
        return $mostRecentResponse;
    }


    /**
     * Specifically find the most recent draft
     * There should be no reason to have multiple drafts, unless bad/corrupted data
     *
     * @return IResponse|null
     */
    public function findMostRecentDraftResponse(): ?IResponse
    {
        if ($this->allResponses->count()) {
            return $this->allResponses->filter(
                function (IResponse $response) {
                    return !$response->getSubmittedAt() || $response->getSubmittedAt()->isNull();
                }
            )->sortBy('createdAt', 'desc')->first();
        }

        return null;
    }


    /**
     * @return IResponse|null
     */
    public function findMostRecentSubmittedResponse()
    {
        /** @var IResponse|null $mostRecentSubmittedResponse */
        $mostRecentSubmittedResponse = null;

        /** @var IResponse $response */
        foreach ($this->allResponses as $response) {
            if ($response->isSubmitted()) {
                if (!$mostRecentSubmittedResponse || $response->getSubmittedAt()->gt($mostRecentSubmittedResponse->getSubmittedAt())) {
                    $mostRecentSubmittedResponse = $response;
                }
            }
        }
        return $mostRecentSubmittedResponse;
    }


    /**
     * Find the authoritative Feedback for an Assessment for this Participant
     *
     * Since 16.5.13, this has absorbed the 'no favour' feedback traversal strategy
     *
     * @return null|IFeedback
     */
    public function findMostRecentInstructorGivenFeedback(): ?IFeedback
    {
        return $this->allFeedbacks
            ->filter(new InstructorGivenFilter())
            ->sortBy('updatedAt', 'desc')
            ->first();
    }


    /**
     * Find the authoritative published Feedback for an Assessment for this Participant
     *
     * Since 16.5.13, this has absorbed the 'no favour' feedback traversal strategy
     *
     * @return null|IFeedback
     */
    public function findMostRecentInstructorGivenPublishedFeedback(): ?IFeedback
    {
        return $this->allFeedbacks
            ->filter(new PublishedFilter())
            ->filter(new InstructorGivenFilter())
            ->sortBy('updatedAt', 'desc')
            ->first();
    }


    /**
     * Find the most recent self-assessed Feedback for an Assessment for this Participant
     *
     * @return null|IFeedback
     */
    public function findMostRecentSelfAssessedFeedback(): ?IFeedback
    {
        return $this->allFeedbacks
            ->filter(new SelfAssessmentFilter())
            ->sortBy('updatedAt', 'desc')
            ->first();
    }


    /**
     * Find the most recent self-assessed published Feedback for an Assessment for this Participant
     *
     * @return null|IFeedback
     */
    public function findMostRecentSelfAssessedPublishedFeedback(): ?IFeedback
    {
        return $this->allFeedbacks
            ->filter(new PublishedFilter())
            ->filter(new SelfAssessmentFilter())
            ->sortBy('updatedAt', 'desc')
            ->first();
    }


    /**
     * Find the authoritative published AND visible Feedback for an Assessment for this Participant
     *
     * This assessment may have a mark visibility setting - if it does, and $viewingUser
     * doesn't match it, they may not view feedback for this assessment.
     *
     * @param User $viewingUser The user who is attempting to view feedback.
     *                          Usually this will come from the user's session.
     *
     * @return null|IFeedback
     */
    public function findAuthoritativeVisibleFeedback(User $viewingUser)
    {
        if (!$this->getAssessable()->isFeedbackVisibleToUser($viewingUser)) {
            return null;
        }
        $publishedOnly = true;
        return $this->getAuthoritativeFeedback($publishedOnly);
    }


    /**
     * Return authoritative normalised mark - optionally filtered to just published marks.
     *
     * @param bool $publishedOnly Should we ignore draft marks?
     * @return null|float
     */
    public function getNormalisedMark($publishedOnly)
    {
        try {
            return $this->plan->getAssessable()->getNormalisedMark($this, $publishedOnly);
        } catch (DomainException $e) {
            // Impossible to throw when calling $this->assessable($this)
            trigger_error($e->getMessage(), E_USER_WARNING);
            return null;
        }
    }


    /**
     * Return authoritative human-readable mark - optionally filtered to just published marks.
     *
     * @param bool $publishedOnly Should we ignore draft marks?
     * @return null|string
     */
    public function getHumanReadableMark($publishedOnly)
    {
        try {
            return $this->plan->getAssessable()->getHumanReadableMark($this, $publishedOnly);
        } catch (DomainException $e) {
            // Impossible to throw when calling $this->assessable($this)
            trigger_error($e->getMessage(), E_USER_WARNING);
            return null;
        }
    }


    /**
     * Return the IResponse responsible for the participant's assessment mark
     *
     * @param $publishedOnly
     * @return null|IResponse
     */
    public function getAuthoritativeResponse($publishedOnly)
    {
        try {
            return $this->plan->getAssessable()->getAuthoritativeResponse($this, $publishedOnly);
        } catch (DomainException $e) {
            // Impossible to throw when calling $this->assessable($this)
            trigger_error($e->getMessage(), E_USER_WARNING);
            return null;
        }
    }


    /**
     * Get the IFeedback responsible for the participant's assessment mark
     *
     * @param $publishedOnly
     * @return null|IFeedback
     */
    public function getAuthoritativeFeedback($publishedOnly)
    {
        try {
            return $this->plan->getAssessable()->getAuthoritativeFeedback($this, $publishedOnly);
        } catch (DomainException $e) {
            // Impossible to throw when calling $this->assessable($this)
            trigger_error($e->getMessage(), E_USER_WARNING);
            return null;
        }
    }


    /**
     * Returns the assessment description for this participant
     *
     * If this participant's plan is dynamic, ignore the plan value and fetch
     * from the assessment; otherwise, use the plan value if it's there.
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        if ($this->plan->getIsDynamic()) {
            return $this->plan->getAssessable()->getDescription();
        }
        return $this->plan->getDescription() ?? $this->plan->getAssessable()->getDescription();
    }


    /**
     * Returns when is the assessment published for this participant.
     *
     * If this participant's plan is dynamic, ignore the plan value and fetch
     * from the assessment; otherwise, use the plan value if it's there.
     *
     * @return Alaress_Date_Format
     */
    public function getPublishDate(): Alaress_Date_Format
    {
        if ($this->plan->getIsDynamic()) {
            return $this->plan->getAssessable()->getPublishAt();
        }
        return $this->coalesceDates($this->plan->getPublishDate(), $this->plan->getAssessable()->getPublishAt());
    }


    /**
     * Returns when is the assessment open for this participant.
     *
     * If this participant's plan is dynamic, ignore the plan value and fetch
     * from the assessment; otherwise, use the plan value if it's there.
     *
     * @return Alaress_Date_Format
     */
    public function getOpenDate(): Alaress_Date_Format
    {
        if ($this->plan->getIsDynamic()) {
            return $this->plan->getAssessable()->getOpenAt();
        }
        return $this->coalesceDates($this->plan->getOpenDate(), $this->plan->getAssessable()->getOpenAt());
    }


    /**
     * Returns when is the assessment due for this participant.
     *
     * If this participant's plan is dynamic, ignore the plan value and fetch
     * from the assessment; otherwise, use the plan value if it's there.
     *
     * @return Alaress_Date_Format
     */
    public function getDueDate(): Alaress_Date_Format
    {
        if ($this->plan->getIsDynamic()) {
            return $this->plan->getAssessable()->getDueAt();
        }
        return $this->coalesceDates($this->plan->getDueDate(), $this->plan->getAssessable()->getDueAt());
    }


    /**
     * Returns when is the assessment closed for this participant.
     *
     * If this participant's plan is dynamic, ignore the plan value and fetch
     * from the assessment; otherwise, use the plan value if it's there.
     *
     * @return Alaress_Date_Format
     */
    public function getCloseDate(): Alaress_Date_Format
    {
        if ($this->plan->getIsDynamic()) {
            return $this->plan->getAssessable()->getCloseAt();
        }
        return $this->coalesceDates($this->plan->getCloseDate(), $this->plan->getAssessable()->getCloseAt());
    }


    /**
     * Returns the first non-null date.
     *
     * You cannot have participants of assessment specs, so we are guaranteed that the assessment is an implementation,
     * in which case we are guaranteed the assessment has dates and NPEs should not occur.
     *
     * @param Alaress_Date_Format|null $planDate
     * @param Alaress_Date_Format      $assessmentDate
     * @return Alaress_Date_Format
     */
    private function coalesceDates(?Alaress_Date_Format $planDate, Alaress_Date_Format $assessmentDate): Alaress_Date_Format
    {
        return null === $planDate || $planDate->isNull() ? $assessmentDate : $planDate;
    }


    /* Getters/Setters
     */

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }


    /**
     * @param User $user
     * @return $this
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }


    /**
     * @return Plan
     */
    public function getPlan(): Plan
    {
        return $this->plan;
    }


    /**
     * @param Plan $plan
     * @return $this
     */
    public function setPlan(Plan $plan): self
    {
        $this->plan = $plan;
        return $this;
    }


    /**
     * @return null|Alaress_Date_Format
     */
    public function getExcludedAt(): ?Alaress_Date_Format
    {
        return $this->excludedAt;
    }


    /**
     * @param null|Alaress_Date_Format $excludedAt
     * @return $this
     */
    public function setExcludedAt(?Alaress_Date_Format $excludedAt): self
    {
        $this->excludedAt = $excludedAt;
        return $this;
    }


    /**
     * Is this an exclusion record?
     *
     * @return bool
     */
    public function isExcluded(): bool
    {
        // Excluded if a non-null date.
        return $this->excludedAt instanceof Alaress_Date_Format && !$this->excludedAt->isNull();
    }


    /**
     * @return IAssessable
     */
    public function getAssessable()
    {
        return $this->plan->getAssessable();
    }


    /**
     * @param IAssessable $assessable
     * @return static
     */
    public function setAssessable(IAssessable $assessable)
    {
        $this->plan->setAssessable($assessable);
        return $this;
    }


    /**
     * @return GenericCollection|IResponse[]
     */
    public function getAllResponses()
    {
        return $this->allResponses;
    }

    /**
     * @return GenericCollection|IResponse[]
     */
    public function getSubmittedResponses()
    {
        return $this->allResponses->filter(
            function (IResponse $response) {
                return $response->getSubmittedAt() && !$response->getSubmittedAt()->isNull();
            }
        );
    }

    /**
     * @param GenericCollection|IResponse[] $allResponses
     * @return static
     * @throws DomainException if the new Collection has incorrect member-item type
     */
    public function setAllResponses(GenericCollection $allResponses)
    {
        $this->allResponses->replaceWith($allResponses);
        return $this;
    }


    /**
     * @return GenericCollection|IFeedback[]
     */
    public function getAllFeedbacks()
    {
        return $this->allFeedbacks;
    }


    /**
     * @param GenericCollection|IFeedback[] $allFeedbacks
     * @return static
     * @throws DomainException if the new Collection has incorrect member-item type
     */
    public function setAllFeedbacks(GenericCollection $allFeedbacks)
    {
        $this->allFeedbacks->replaceWith($allFeedbacks);
        return $this;
    }
}
