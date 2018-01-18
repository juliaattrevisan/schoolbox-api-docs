<?php
/**
 * ValidSelfReflection
 *
 * This (assessment, user) combination is currently valid for self-reflection
 *
 * TODO: Can you self-reflect on behalf of someone else, via SOBO?
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Response;

use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\User;

class ValidSelfReflectionWithResponse extends ValidSelfReflection
{

    /**
     * The currently logged-in user can self-reflect on the specified Participant's IResponse
     * only when the following conditions are met
     *
     * @param User        $user
     * @param Participant $participant
     * @return bool
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
                && $this->isSelfReflectable($participant)
                && $this->hasDraftResponse($participant);
        }

        return false;
    }
}
