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

use \Schoolbox\User;
use \Schoolbox\Collection;

class Participant
{
    /**
     * The User to whom all these Responses and Feedbacks are relevant
     *
     * @var User
     */
    protected $user;

    /**
     * A Collection of IResponse instances that Students spawn
     *
     * @var Collection|IResponse[]
     */
    protected $allResponses;

    /**
     * A Collection of IFeedback instances that Teachers spawn
     *
     * @var Collection|IFeedback[]
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
        $this->setAllResponses(new Collection());
        $this->setAllFeedbacks(new Collection());
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
        $favorFloating = false;
        if($favorFloating){
            return $this->getMostRecentFeedback_favorFloating($assessable, $publishedOnly);
        } else {
            return $this->getMostRecentFeedback_noFavor($assessable, $publishedOnly);
        }
    }

    /**
     * Find the most recent IFeedback for an IAssessable for this Participant
     *
     * All Feedbacks are considered equal, compared purely by dates
     *
     * Consider following scenario for DueWork.
     * * Student submits $response1 in 2010 and receives $feedback1 on it in 2014.
     * * Student submits $response2 in 2011 and receives $feedback2a on it in 2011 and $feedback2b in 2012
     * * Student submits $response3 in 2013
     * * Teacher provides floating $feedback4 in 2010
     *
     * In this case, the authoritative feedback is the linked $feedback1 in 2014,
     * even though it is attached to an old response and we have an overall floating $feedback4 in 2010
     *
     * Need to also apply the $publishedOnly filter to all traversals.
     *
     * @param IAssessable $assessable
     * @param bool $publishedOnly
     * @return null|IFeedback
     */
    private function getMostRecentFeedback_noFavor(IAssessable $assessable, $publishedOnly)
    {
        /** @var IFeedback $mostRecentFeedback */
        $mostRecentFeedback = null;

        foreach ($this->findAllFeedbackForAssessable($assessable) as $feedback) {
            if (!$publishedOnly || $feedback->isPublished()) {
                if (!$mostRecentFeedback || $feedback->getUpdatedAt()->gt($mostRecentFeedback->getUpdatedAt())) {
                    $mostRecentFeedback = $feedback;
                }
            }
        }
        return $mostRecentFeedback;
    }

    /**
     * Find the most recent IFeedback for an IAssessable for this Participant
     *
     * Floating Feedback trumps linked Feedback
     *
     * Consider following scenario for DueWork.
     * * Student submits $response1 in 2010 and receives $feedback1 on it in 2014.
     * * Student submits $response2 in 2011 and receives $feedback2a on it in 2011 and $feedback2b in 2012
     * * Student submits $response3 in 2013
     * * Teacher provides floating $feedback4 in 2010
     *
     * In this case, the authoritative feedback is the floating $feedback in 2010,
     * even though is predates the linked $feedback2b of 2012 and linked $feedback1 pf 2014.
     *
     * If no floating feedback exists, we would fall back to the latest feedback on the latest
     * response, ie $feedback2b. Even though it predates $feedback1.
     *
     * Need to also apply the $publishedOnly filter to all traversals.
     *
     * @param IAssessable $assessable
     * @param bool $publishedOnly
     * @return null|IFeedback
     */
    private function getMostRecentFeedback_favorFloating(IAssessable $assessable, $publishedOnly)
    {

        /** @var IFeedback $mostRecentFloatingFeedback */
        $mostRecentFloatingFeedback = null;

        foreach ($this->findAllFeedbackForAssessable($assessable) as $feedback) {
            if (!$publishedOnly || $feedback->isPublished()) {
                if(!$feedback->getResponse() instanceof IResponse){
                    if (!$mostRecentFloatingFeedback || $feedback->getUpdatedAt()->gt($mostRecentFloatingFeedback->getUpdatedAt())) {
                        $mostRecentFloatingFeedback = $feedback;
                    }
                }
            }
        }

        if($mostRecentFloatingFeedback){
            return $mostRecentFloatingFeedback;
        }

        /** @var IResponse $latestResponseWithFeedback */
        $latestResponseWithFeedback = null;

        foreach($this->findAllResponsesForAssessable($assessable) as $response){
            if(!$latestResponseWithFeedback || $response->getUpdatedAt()->gt($latestResponseWithFeedback->getUpdatedAt())){
                if(!$publishedOnly){
                    if(count($response->getAllFeedback())){
                        $latestResponseWithFeedback = $response;
                    }
                } else {
                    foreach($response->getAllFeedback() as $feedback){
                        if($feedback->isPublished()){
                            $latestResponseWithFeedback = $response;
                            break;
                        }
                    }
                }
            }
        }

        // No Response with Feedback?
        if(!$latestResponseWithFeedback){
            return null;
        }

        /** @var IFeedback $mostRecentLinkedFeedback */
        $mostRecentLinkedFeedback = null;

        foreach($latestResponseWithFeedback->getAllFeedback() as $feedback){
            if (!$publishedOnly || $feedback->isPublished()) {
                if (!$mostRecentLinkedFeedback || $feedback->getUpdatedAt()->gt($mostRecentLinkedFeedback->getUpdatedAt())) {
                    $mostRecentLinkedFeedback = $feedback;
                }
            }
        }
        return $mostRecentLinkedFeedback;
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
     * @return Collection|IResponse[]
     */
    public function getAllResponses()
    {
        return $this->allResponses;
    }

    /**
     * @param Collection|IResponse[] $allResponses
     */
    public function setAllResponses($allResponses)
    {
        $this->allResponses = $allResponses;
    }

    /**
     * @return Collection|IFeedback[]
     */
    public function getAllFeedbacks()
    {
        return $this->allFeedbacks;
    }

    /**
     * @param Collection|IFeedback[] $allFeedbacks
     */
    public function setAllFeedbacks($allFeedbacks)
    {
        $this->allFeedbacks = $allFeedbacks;
    }
}