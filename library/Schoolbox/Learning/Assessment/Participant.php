<?php
/**
 * Participant
 *
 * A "flattened" version of the Submission/Completion/Attempt--Feedback
 * hierarchy that currently exists, so you don't need to traverse so much
 * to find particular Feedbacks for a particular User
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\FeedbackStrategy\FavourFloating;
use Schoolbox\Learning\Assessment\FeedbackStrategy\IFeedbackTraversalStrategy;
use Schoolbox\Learning\Assessment\FeedbackStrategy\NoFavour;
use Schoolbox\Learning\MarkScheme\Completion as CompletionMarkScheme;
use \Schoolbox\User;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkSubmission;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Assessment\Quiz\Feedback\Model as QuizReview;
use Schoolbox\Learning\Assessment\Task\Response\Model as TaskResponse;
use Schoolbox\Learning\Assessment\Task\Feedback\Model as TaskFeedback;

class Participant
{
    /**
     * Whether when determining the current mark for an Assessment, we prefer
     * overall feedback over feedback linked to specific Responses.
     *
     * @TODO - Needs A/B testing, or make this a user-config option, or provide some UI to give the choice to the user.
     *
     * @var bool
     */
    const FAVOR_FLOATING = false;

    /**
     * How to determine what constitutes the current overall mark for a given User and Assessment
     *
     * @var IFeedbackTraversalStrategy
     */
    protected $feedbackStrategy;

    /**
     * The User to whom all these Responses and Feedbacks are relevant
     *
     * @var User
     */
    protected $user;

    /**
     * The Assessment which these Responses and Feedbacks relate to
     *
     * @var IAssessable
     */
    private $assessable;

    /**
     * A Collection of IResponse instances that Students spawn
     *
     * Null for Projects
     *
     * @var GenericCollection|IResponse[]|null
     */
    protected $allResponses;

    /**
     * A Collection of IFeedback instances that Teachers spawn
     *
     * Null for Projects
     *
     * @var GenericCollection|IFeedback[]|null
     */
    protected $allFeedbacks;

    /**
     * Participant constructor.
     *
     * @param User $user
     * @param IAssessable $assessable
     */
    public function __construct(User $user, IAssessable $assessable)
    {
        $this->setUser($user);
        $this->setAssessable($assessable);

        $this->allResponses = new GenericCollection($this->getAllowedResponseType() ?: IResponse::class);

        $this->allFeedbacks = new GenericCollection($this->getAllowedFeedbackType() ?: IFeedback::class);

        $this->feedbackStrategy = self::FAVOR_FLOATING ? new FavourFloating() : new NoFavour();
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
        // Special case for Task
        if($this->assessable instanceof Task){
            /** @var TaskResponse $mostRecentResponse */
            $mostRecentResponse = $this->findMostRecentSubmittedResponse();
            return !$mostRecentResponse || !$mostRecentResponse->getMarkedComplete();
        }

        $latestSubmittedResponse = $this->findMostRecentSubmittedResponse();
        $latestPublishedFeedback = $this->findMostRecentPublishedFeedback();

        // No Responses and No Feedback?
        if(!$latestSubmittedResponse && !$latestPublishedFeedback){
            // No interaction yet - waiting for student to submit or teacher to give mark
            return true;
        }

        // We either have a submitted response, or a published feedback, or both.
        // All these scenarios imply student does not need to respond.
        return false;
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
        // Special case for Task.
        if($this->assessable instanceof Task){
            /** @var TaskResponse $latestSubmittedResponse */
            $latestSubmittedResponse = $this->findMostRecentSubmittedResponse();
            return $latestSubmittedResponse
                && $latestSubmittedResponse->isSelfAssessed()
                && $latestSubmittedResponse->getMarkedComplete();
        }

        // No responses, or latest response has published feedback already ?
        $latestSubmittedResponse = $this->findMostRecentSubmittedResponse();
        if (!$latestSubmittedResponse || $latestSubmittedResponse->hasPublishedFeedback()) {
            return false;
        }

        // One or more responses and no feedback ?
        $latestPublishedFeedback = $this->findMostRecentPublishedFeedback();
        if (!$latestPublishedFeedback) {
            return true;
        }

        // Was the latest student response submitted after the latest mark was published?
        return $latestSubmittedResponse->getSubmittedAt() > $latestPublishedFeedback->getPublishedAt();
    }


    /**
     * Has the teacher marked this participant as not-explicitly-incomplete, and
     * the student has not subsequently submitted/re-submitted.
     *
     * Represented in UI as GREEN CIRCLE
     */
    public function isReviewComplete()
    {
        // Special case for Task
        if($this->assessable instanceof Task){
            /** @var TaskResponse $latestSubmittedResponse */
            $latestSubmittedResponse = $this->findMostRecentSubmittedResponse();
            return $latestSubmittedResponse
                && $latestSubmittedResponse->getMarkedComplete()
                && !$latestSubmittedResponse->isSelfAssessed();
        }

        return  $this->hasPublishedFeedback() && !$this->isFeedbackRequired();
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
        foreach($this->allResponses as $response){
            if($response->isSubmitted()){
                return true;
            }
        }
        return false;
    }


    /**
     * Has a teacher given a published mark to this participant.
     *
     * For clarity - a self-assessed task, while complete, is not marked by teacher
     * @see $this->hasMark to include self-assessed marks.
     *
     * @return bool
     */
    public function hasPublishedFeedback()
    {
        foreach($this->allFeedbacks as $feedback){
            if($feedback->isPublished()){
                return true;
            }
        }
        return false;
    }


    /**
     * Does this student have a mark for the assessment.
     *
     * This includes marks given by the teacher, and
     * self-assessed marks for a task (even if self-assessed as 'Incomplete')
     *
     * @see $this->isMarkedByTeacher for teacher-assigned-only marks
     * @return bool
     */
    public function hasMark()
    {
        // Special case for task - can be marked by student or teacher.
        if($this->assessable instanceof Task){
            // Has the task been marked ?
            // Note this distinguishes between implicit 'Incomplete' and explicit 'Incomplete'.
            // The former returns FALSE, while the latter returns TRUE
            return $this->allResponses->count() > 0;
        }

        // Default case
        return $this->hasPublishedFeedback();
    }


    /**
     * @return IResponse|null
     */
    public function findMostRecentResponse()
    {
        /** @var IResponse $mostRecentResponse */
        $mostRecentResponse = null;
        foreach ($this->allResponses as $response) {
            if (!$mostRecentResponse || $response->getUpdatedAt()->gt($mostRecentResponse->getUpdatedAt())) {
                $mostRecentResponse = $response;
            }
        }
        return $mostRecentResponse;
    }


    /**
     * @return IResponse|null
     */
    public function findMostRecentSubmittedResponse()
    {
        /** @var IResponse|null $mostRecentSubmittedResponse */
        $mostRecentSubmittedResponse = null;
        foreach($this->allResponses as $response){
            if($response->isSubmitted()){
                if(!$mostRecentSubmittedResponse || $response->getSubmittedAt()->gt($mostRecentSubmittedResponse->getSubmittedAt())){
                    $mostRecentSubmittedResponse = $response;
                }
            }
        }
        return $mostRecentSubmittedResponse;
    }

    /**
     * Find the authoritative Feedback for an Assessment for this Participant
     *
     * @return null|IFeedback
     */
    public function findMostRecentFeedback()
    {
        return $this->feedbackStrategy->getMostRecentFeedback($this, false);
    }

    /**
     * Find the authoritative published Feedback for an Assessment for this Participant
     *
     * @return null|IFeedback
     */
    public function findMostRecentPublishedFeedback()
    {
        return $this->feedbackStrategy->getMostRecentFeedback($this, true);
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
    public function findMostRecentVisibleFeedback(User $viewingUser)
    {
        if (!$this->getAssessable()->isFeedbackVisibleToUser($viewingUser)) {
            return null;
        }
        return $this->findMostRecentPublishedFeedback();
    }

    /**
     * Returns this Participant's published Human-Readable mark
     *
     * @return null|string
     */
    public function getPublishedHumanReadableMark()
    {
        // Special case for Completion MarkScheme (it derives the mark from the state of the latest response)
        $markScheme = $this->assessable->getMarkScheme();
        if($markScheme instanceof CompletionMarkScheme){
            $mostRecentSubmittedResponse = $this->findMostRecentSubmittedResponse();

            return $mostRecentSubmittedResponse
                ? $markScheme->getHumanReadableMark(null, $mostRecentSubmittedResponse)
                : null;
        }

        $feedback = $this->findMostRecentPublishedFeedback();
        return $feedback ? $feedback->getHumanReadableMark() : null;
    }


    /**
     * Determine what sort of objects are permitted in the Response collection
     *
     * @return string|null
     */
    protected function getAllowedResponseType()
    {
        if ($this->assessable instanceof Quiz) {
            return QuizAttempt::class;
        } elseif ($this->assessable instanceof DueWork) {
            return DueWorkSubmission::class;
        } elseif ($this->assessable instanceof Task) {
            return TaskResponse::class;
        } elseif ($this->assessable instanceof Project) {
            return null;
        } else {
            throw new \UnexpectedValueException('Unknown IAssessable: ' . get_class($this->assessable));
        }
    }

    /**
     * Determine what sort of objects are permitted in the Feedback collection
     *
     * @return string|null
     */
    protected function getAllowedFeedbackType()
    {
        if ($this->assessable instanceof Quiz) {
            return QuizReview::class;
        } elseif ($this->assessable instanceof DueWork) {
            return DueWorkFeedback::class;
        } elseif ($this->assessable instanceof Task) {
            return TaskFeedback::class;
        } elseif ($this->assessable instanceof Project) {
            return null;
        } else {
            throw new \UnexpectedValueException('Unknown IAssessable: ' . get_class($this->assessable));
        }
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
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * @return IAssessable
     */
    public function getAssessable()
    {
        return $this->assessable;
    }

    /**
     * @param IAssessable $assessable
     * @return static
     */
    public function setAssessable(IAssessable $assessable)
    {
        $this->assessable = $assessable;
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
     * @param GenericCollection|IResponse[] $allResponses
     * @return static
     * @throws \DomainException if the new Collection has incorrect member-item type
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
     * @throws \DomainException if the new Collection has incorrect member-item type
     */
    public function setAllFeedbacks(GenericCollection $allFeedbacks)
    {
        $this->allFeedbacks->replaceWith($allFeedbacks);
        return $this;
    }
}
