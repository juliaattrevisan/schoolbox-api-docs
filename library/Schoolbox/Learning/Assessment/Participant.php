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
use \Schoolbox\User;
use \DomainException;

class Participant
{
    /**
     * The User to whom all these Responses and Feedbacks are relevant
     *
     * @var User
     */
    private $user;

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
     * @param IAssessable $assessable
     */
    public function __construct(User $user, IAssessable $assessable)
    {
        $this->setUser($user);
        $this->setAssessable($assessable);

        $this->allResponses = new GenericCollection($assessable->getResponseClass());
        $this->allFeedbacks = new GenericCollection($assessable->getFeedbackClass());
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
     * @throws DomainException
     */
    public function isResponseRequired()
    {
        return $this->assessable->isResponseRequired($this);
    }


    /**
     * Does the teacher need to mark this participant ?
     *
     * Represented in UI as ORANGE DIAMOND
     *
     * This is a teacher-perspective function. ie, does the teacher need to do anything?
     * eg, Give a mark? Publish a draft? Mark off as 'complete' ?
     *
     * @throws DomainException if $user is not a participant of the participation assessable.
     * @return bool
     */
    public function isFeedbackRequired()
    {
        return $this->assessable->isFeedbackRequired($this);
    }


    /**
     * Has the teacher marked this participant as not-explicitly-incomplete, and
     * the student has not subsequently submitted/re-submitted.
     *
     * @throws DomainException if $user is not a participant of the participation assessable.
     * Represented in UI as GREEN CIRCLE
     */
    public function isReviewComplete()
    {
        return $this->assessable->isReviewComplete($this);
    }


    /**
     * Has this participant made ANY kind of (submitted) response to this assessment?
     *
     * Includes teacher Submitting-On-Behalf-Of (S-O-B-O)
     *
     * Important: This will still return TRUE if task has been marked as 'incomplete'.
     *
     * @throws DomainException if $user is not a participant of the participation assessable.
     * @return bool
     */
    public function hasSubmittedResponse()
    {
        return $this->assessable->hasSubmittedResponse($this);
    }


    /**
     * Has a teacher given a published mark to this participant.
     *
     * For clarity - a self-assessed task, while complete, is not marked by teacher
     * @see $this->hasMark to include self-assessed marks.
     *
     * @throws DomainException if $user is not a participant of the participation assessable.
     * @return bool
     */
    public function hasPublishedFeedback()
    {
        return $this->assessable->hasPublishedFeedback($this);
    }


    /**
     * Does this student have a mark for the assessment.
     *
     * This includes marks given by the teacher, and
     * self-assessed marks for a task (even if self-assessed as 'Incomplete')
     *
     * @see $this->isMarkedByTeacher for teacher-assigned-only marks
     * @throws DomainException if $user is not a participant of the participation assessable.
     * @return bool
     */
    public function hasMark()
    {
        return $this->assessable->hasMark($this);
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
        /** @var IFeedback $mostRecentFeedback */
        $mostRecentFeedback = null;

        foreach ($this->allFeedbacks as $feedback) {
            if (!$mostRecentFeedback || $feedback->getUpdatedAt()->gt($mostRecentFeedback->getUpdatedAt())) {
                $mostRecentFeedback = $feedback;
            }
        }
        return $mostRecentFeedback;
    }

    /**
     * Find the authoritative published Feedback for an Assessment for this Participant
     *
     * @return null|IFeedback
     */
    public function findMostRecentPublishedFeedback()
    {
        /** @var IFeedback $mostRecentFeedback */
        $mostRecentFeedback = null;

        foreach ($this->allFeedbacks as $feedback) {
            if ($feedback->isPublished() && (!$mostRecentFeedback || $feedback->getUpdatedAt()->gt($mostRecentFeedback->getUpdatedAt()))) {
                    $mostRecentFeedback = $feedback;
            }
        }
        return $mostRecentFeedback;
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
        return $this->assessable->getMarkScheme()->getPublishedHumanReadableMark($this);
    }

    /**
     * Returns this Participant's most recent Human-Readable mark (includes draft marks)
     *
     * @return null|string
     */
    public function getMostRecentHumanReadableMark()
    {
        return $this->assessable->getMarkScheme()->getMostRecentHumanReadableMark($this);
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
