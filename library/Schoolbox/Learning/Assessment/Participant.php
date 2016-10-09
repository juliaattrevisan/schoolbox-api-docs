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
use Schoolbox\Learning\Assessment\FeedbackStrategy\IFeedbackStrategy;
use Schoolbox\Learning\Assessment\FeedbackStrategy\NoFavour;
use \Schoolbox\User;
use \Schoolbox\Collection;

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
     * @var IFeedbackStrategy
     */
    protected $feedbackStrategy;

    /**
     * The User to whom all these Responses and Feedbacks are relevant
     *
     * @var User
     */
    protected $user;

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
     */
    public function __construct(User $user)
    {
        $this->setUser($user);
        $this->setAllResponses(new GenericCollection());
        $this->setAllFeedbacks(new GenericCollection());
        $this->feedbackStrategy = self::FAVOR_FLOATING ? new FavourFloating() : new NoFavour();
    }

    /**
     * Find all the Responses that were spawned for a particular IAssessable
     *
     * @param IAssessable $assessable
     * @return Collection|IResponse[]
     */
    public function findAllResponsesForAssessable(IAssessable $assessable)
    {
        $responses = new Collection();

        foreach ($this->getAllResponses() as $response) {
            $responseAssessable = $response->getAssessable();
            if ($assessable->getId() == $responseAssessable->getId()) {
                $responses->add($response);
            }
        }
        return $responses;
    }

    /**
     * Find all the Feedbacks that were spawned for a particular IAssessable
     *
     * @param IAssessable $assessable
     * @return Collection|IFeedback[]
     */
    public function findAllFeedbackForAssessable(IAssessable $assessable)
    {
        $feedbacks = new Collection();

        foreach ($this->getAllFeedbacks() as $feedback) {
            $feedbackAssessable = $feedback->getAssessable();
            if ($assessable->getId() == $feedbackAssessable->getId()) {
                $feedbacks->add($feedback);
            }
        }
        return $feedbacks;
    }

    /**
     * Whether this Participant has unmarked Response to the specified Assessment
     *
     * @param IAssessable $assessable
     * @return bool
     */
    public function isMarkingRequired(IAssessable $assessable)
    {
        if(!count($this->allResponses)){
            return false;
        }
        if(!count($this->allFeedbacks)){
            return true;
        }
        $latestResponse = $this->findMostRecentResponse($assessable);
        $latestFeedback = $this->findMostRecentFeedback($assessable);
        return $latestResponse->getUpdatedAt() > $latestFeedback->getUpdatedAt();
    }

    /**
     * This Participant received a Feedback for the $assessable
     *
     * @param IAssessable $assessable
     * @return bool
     */
    public function isAssessableMarked(IAssessable $assessable)
    {
        $feedbacks = $this->findAllFeedbackForAssessable($assessable);
        return count($feedbacks) > 0;
    }

    /**
     * This Participant received a Published Feedback for the $assessable
     *
     * @param IAssessable $assessable
     * @return bool
     */
    public function isAssessablePublished(IAssessable $assessable)
    {
        return $this->findMostRecentPublishedFeedback($assessable) instanceof IFeedback;
    }


    /**
     * @param IAssessable $assessable
     * @return IResponse|null
     */
    public function findMostRecentResponse(IAssessable $assessable)
    {
        /** @var IResponse $mostRecentResponse */
        $mostRecentResponse = null;
        foreach ($this->allResponses as $response) {
            if ($assessable->getId() === $response->getAssessable()->getId()) {
                if (!$mostRecentResponse || $response->getUpdatedAt()->gt($mostRecentResponse->getUpdatedAt())) {
                    $mostRecentResponse = $response;
                }
            }
        }
        return $mostRecentResponse;
    }

    /**
     * Find the most recent IFeedback for an IAssessable for this Participant
     *
     * @param IAssessable $assessable
     * @return null|IFeedback
     */
    public function findMostRecentFeedback(IAssessable $assessable)
    {
        return $this->getMostRecentFeedback($assessable, false);
    }

    /**
     * Find the most recent published IFeedback for an IAssessable for this Participant
     *
     * @param IAssessable $assessable
     * @return null|IFeedback
     */
    public function findMostRecentPublishedFeedback(IAssessable $assessable)
    {
        return $this->getMostRecentFeedback($assessable, true);
    }

    /**
     * Gets the authoritative Feedback for an Assessment for this Participant
     *
     * @param IAssessable $assessable
     * @param bool $publishedOnly
     * @return null|IFeedback
     */
    private function getMostRecentFeedback(IAssessable $assessable, $publishedOnly)
    {
        return $this->feedbackStrategy->getMostRecentFeedback($this, $assessable, $publishedOnly);
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