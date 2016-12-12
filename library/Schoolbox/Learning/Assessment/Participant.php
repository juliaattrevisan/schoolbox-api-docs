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

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\FeedbackStrategy\FavourFloating;
use Schoolbox\Learning\Assessment\FeedbackStrategy\IFeedbackTraversalStrategy;
use Schoolbox\Learning\Assessment\FeedbackStrategy\NoFavour;
use \Schoolbox\User;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkSubmission;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Assessment\Quiz\Feedback\Model as QuizReview;
use Schoolbox\Learning\Assessment\Task\Completion\Model as TaskCompletion;
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
     * Whether this Participant has unmarked Response
     *
     * @return bool
     */
    public function isMarkingRequired()
    {
        // No responses, or latest response does not require marking
        $latestResponse = $this->findMostRecentSubmittedResponse();
        if (!$latestResponse || !$latestResponse->requiresMarking()) {
            return false;
        }

        // No feedback
        $latestFeedback = $this->findMostRecentFeedback();
        if (!$latestFeedback) {
            return true;
        }

        /* Otherwise determine based on the timeline of events
         */
        return $latestResponse->getUpdatedAt() > $latestFeedback->getUpdatedAt();
    }

    /**
     * @deprecated use isMarked()
     * This Participant received a Feedback for the $assessable
     *
     * @return bool
     */
    public function isAssessableMarked()
    {
        return count($this->allFeedbacks) > 0;
    }

    /**
     * Has this participant received ANY (published) feedback for this assessment?
     *
     * @return bool
     */
    public function isMarked()
    {
        return $this->allFeedbacks->filter(function (IFeedback $feedback) { return $feedback->isPublished(); })->count() > 0;
    }

    /**
     * Has this participant made ANY kind of (submitted) response to this assessment?
     *
     * @return bool
     */
    public function isResponded()
    {
        return $this->allResponses->filter(function (IResponse $response) { return $response->isSubmitted(); })->count() > 0;
    }

    /**
     * Has this participant completed this assessment?
     *
     * The logic for whether an assessment is completed is determined by the
     * feedback within this participant - if the latest feedback is marked
     * completed, the assessment is.
     *
     * @return bool
     */
    public function isComplete()
    {
        $mostRecent = $this->findMostRecentPublishedFeedback();
        return ($mostRecent instanceof IFeedback)
            ? $mostRecent->isComplete()
            : false;
    }

    /**
     * @deprecated misnomer, use hasPublishedFeedback()
     * This Participant received a Published Feedback for the $assessable
     *
     * @return bool
     */
    public function isAssessablePublished()
    {
        return $this->findMostRecentPublishedFeedback() instanceof IFeedback;
    }

    /**
     * @return bool
     */
    public function hasPublishedFeedback()
    {
        return $this->findMostRecentPublishedFeedback() instanceof IFeedback;
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
        $filteredResponses = $this->allResponses->filter(function(IResponse $response) {
            return $response->isSubmitted();
        });

        return $filteredResponses->sortBy('submittedAt', 'desc')->first();
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
            return TaskCompletion::class;
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
        $this->allResponses->assertSameItemClass($allResponses);
        $this->allResponses = $allResponses;
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
        $this->allFeedbacks->assertSameItemClass($allFeedbacks);
        $this->allFeedbacks = $allFeedbacks;
        return $this;
    }

    /**
     * Get a little data structure of Unpublished marks in both Human Readable
     * and Normalised formats
     *
     * Maybe this can be broken down into getMark(), getPublishedMark(), getUnpublishedMark()
     * methods but for now, just throw them all together
     *
     * @return array
     */
    public function getMark()
    {
        return [
            'published' => [
                'humanReadable' => $this->getAssessable()->getHumanReadableMark($this->getUser(), true),
                'normalised' => $this->getAssessable()->getNormalisedMark($this->getUser(), true)
            ],
            'unpublished' => [
                'humanReadable' => $this->getAssessable()->getHumanReadableMark($this->getUser(), false),
                'normalised' => $this->getAssessable()->getNormalisedMark($this->getUser(), false)
            ]
        ];
    }
}
