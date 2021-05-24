<?php

/**
 * ValidSelfReflection
 *
 * This (assessment, user) combination is currently valid for self-grading,
 * and also has a draft response that can be published.
 *
 * TODO: Can you self-reflect on behalf of someone else, via SOBO?
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Response;

use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\User;
use UnexpectedValueException;

class ValidSelfGradableWithResponse extends ValidSelfGradable
{
    /**
     * The currently logged-in user can self-reflect on the specified Participant's IResponse
     * only when the following conditions are met
     *
     * @param User        $user
     * @param Participant $participant
     * @return bool
     * @throws UnexpectedValueException If due work model not hydrated
     */
    public function isSatisfiedBy(User $user, Participant $participant): bool
    {
        /* Since I can't guarantee that the Participation's IAssessable is a DueWork\Model
         * there's a sequential dependency that must be obeyed - the IAssessable has to be a
         * DueWork\Model before an isSelfAssessable() check can be performed, otherwise
         * you'll experience a fatal undefined method scenario
         */
        if ($this->isDueWork($participant)) {
            return $this->isViewingOwnParticipation($user, $participant)
                && $this->isSelfGradable($participant)
                && $this->hasDraftResponse($participant);
        }

        return false;
    }
}
