<?php
/**
 * ValidSelfAssessable
 *
 * This (assessment, user) combination is currently valid for any type of
 * self-assessment (self-reflection only, or self-grade)
 *
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Response;

use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\User;
use UnexpectedValueException;

class ValidSelfAssessable
{

    /**
     * The currently-logged-in user
     *
     * @var User
     */
    protected $user;

    /**
     * @var IAssessable
     */
    protected $assessment;

    /**
     * @var Participant
     */
    protected $participant;


    /**
     * Helper method to avoid repeated type-hints and instanceof checks
     * Will throw a type error if participant is not for a dueWork
     *
     * @param Participant $participant
     * @return DueWork\Model|IAssessable
     */
    protected function getDueWork(Participant $participant): DueWork\Model
    {
        return $participant->getAssessable();
    }


    /**
     * Participant is the same as current logged-in user
     *
     * @param User        $user
     * @param Participant $participant
     * @return bool
     */
    protected function isViewingOwnParticipation(User $user, Participant $participant): bool
    {
        return $user->getId() === $participant->getUser()->getId();

        /* TODO: Can you self-reflect on behalf of someone else, via SOBO?
         */
    }


    /**
     * The IAssessable is a Due Work
     * Other assessment types don't support self-reflection here
     *
     * @param Participant $participant
     * @return bool
     */
    protected function isDueWork(Participant $participant): bool
    {
        return $participant->getAssessable() instanceof DueWork\Model;
    }


    /**
     * The IAssessable is set to Self-Reflect or Self-Grade
     *
     * @param Participant $participant
     * @return bool
     * @throws UnexpectedValueException If due-work model is not hydrated
     */
    protected function isSelfAssessable(Participant $participant): bool
    {
        return $this->getDueWork($participant)->isSelfAssessable();
    }


    /**
     * The Participant has at least one IResponse
     *
     * I am making a logical leap and assuming that the IResponse is a draft when
     * attempting a self-reflection, and only Submitted officially after that step
     * so I can't use $this->participant->hasSubmittedResponse()
     *
     * @param Participant $participant
     * @return bool
     */
    protected function hasDraftResponse(Participant $participant): bool
    {
        $mostRecentResponse = $participant->findMostRecentResponse();

        return $mostRecentResponse && !$mostRecentResponse->isSubmitted();
    }


    /**
     * The user can start the self-reflection process
     *
     * @param User        $user
     * @param Participant $participant
     * @return bool
     * @throws UnexpectedValueException If due-work model is not hydrated
     */
    public function isSatisfiedBy(User $user, Participant $participant): bool
    {
        /* Since I can't guarantee that the Participation's IAssessable is a DueWork\Model
         * there's a sequential dependency that must be obeyed - the IAssessable has to be a
         * DueWork\Model before an isSelfReflectable() check can be performed, otherwise
         * you'll experience a fatal undefined method scenario
         */
        if ($this->isDueWork($participant)) {
            return $this->isViewingOwnParticipation($user, $participant)
                && $this->isSelfAssessable($participant);
        }

        return false;
    }
}
