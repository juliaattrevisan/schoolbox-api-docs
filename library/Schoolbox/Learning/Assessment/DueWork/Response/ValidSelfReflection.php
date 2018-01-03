<?php
/**
 * ValidSelfReflection
 *
 * This (assessment, user) combination is valid for self-reflection
 *
 * TODO: Add unit tests
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Response;

use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\User;


class ValidSelfReflection
{

    /**
     * The currently-logged-in user
     *
     * @var User
     */
    private $user;

    /**
     * @var IAssessable
     */
    private $assessment;

    /**
     * @var Participant
     */
    private $participant;

    /**
     * ValidSelfReflection constructor.
     *
     * @param User   $user  Current login
     * @param Participant $participant
     * @internal param null|User $currentUser
     */
    public function __construct(User $user, Participant $participant)
    {
        $this->user = $user;
        $this->assessment = $participant->getAssessable();
        $this->participant = $participant;
    }

    /**
     * Participant is the same as current logged-in user
     *
     * @return bool
     */
    private function isViewingOwnParticipation(): bool
    {
        return $this->user->getId() === $this->participant->getUser()->getId();

        /* TODO: Can you self-reflect on behalf of someone else, via SOBO?
         */
    }

    /**
     * The IAssessable is a Due Work
     * Other assessment types don't support self-reflection here
     *
     * @return bool
     */
    private function isDueWork(): bool
    {
        return $this->assessment instanceof DueWork\Model;
    }

    /**
     * The IAssessable is set to Self-Reflect
     */
    private function isSelfReflectable(): bool
    {
        return $this->assessment->isSelfReflectable();

        /* TODO: ??????? not an interface method?
         */
    }

    /**
     * The Participant has at least one IResponse
     *
     * I am making a logical leap and assuming that the IResponse is a draft when
     * attempting a self-reflection, and only Submitted officially after that step
     * so I can't use $this->participant->hasSubmittedResponse()
     *
     * @return bool
     */
    private function hasResponse(): bool
    {
        return $this->participant->isResponseRequired();
    }

    /**
     * The currently logged-in user can self-reflect on the specified Participant's IResponse
     * only when the following conditions are met
     */
    public function isSatisfied(): bool
    {
        return $this->isViewingOwnParticipation()
            && $this->isDueWork()
            && $this->isSelfReflectable()
            && $this->hasResponse();
    }
}