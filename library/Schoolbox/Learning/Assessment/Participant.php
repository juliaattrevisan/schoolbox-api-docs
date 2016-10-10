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
use \Schoolbox\User;

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
     * @var GenericCollection|IResponse[]
     */
    protected $allResponses;

    /**
     * A Collection of IFeedback instances that Teachers spawn
     *
     * @var GenericCollection|IFeedback[]
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
        $this->setAllResponses(new GenericCollection());
        $this->setAllFeedbacks(new GenericCollection());
        $this->feedbackStrategy = self::FAVOR_FLOATING ? new FavourFloating() : new NoFavour();
    }

    /**
     * Whether this Participant has unmarked Response
     *
     * @return bool
     */
    public function isMarkingRequired()
    {
        if(!count($this->allResponses)){
            return false;
        }
        if(!count($this->allFeedbacks)){
            return true;
        }
        $latestResponse = $this->findMostRecentResponse();
        $latestFeedback = $this->findMostRecentFeedback();
        return $latestResponse->getUpdatedAt() > $latestFeedback->getUpdatedAt();
    }

    /**
     * This Participant received a Feedback for the $assessable
     *
     * @return bool
     */
    public function isAssessableMarked()
    {
        return count($this->allFeedbacks) > 0;
    }

    /**
     * This Participant received a Published Feedback for the $assessable
     *
     * @return bool
     */
    public function isAssessablePublished()
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
     */
    public function setAllResponses($allResponses)
    {
        $this->allResponses = $allResponses;
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
     */
    public function setAllFeedbacks($allFeedbacks)
    {
        $this->allFeedbacks = $allFeedbacks;
    }
}