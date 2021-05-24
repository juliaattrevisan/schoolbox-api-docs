<?php

/**
 *
 * @author      Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use InvalidArgumentException;
use Schoolbox\Error;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Authorizer\Rule\AbstractParticipationRule;
use Schoolbox\User;
use UnexpectedValueException;

/**
 * Class CanSelfAssess
 *
 * In $params we need:
 *  - 'participant' Participant:
 *      - assessable fully hydrated
 *
 * The rule is:
 *  - if the assessment is self-assessable and you are the participant, you are good
 *
 *
 * @package Schoolbox\Learning\Authorizer\Rule
 */
class CanSelfAssess extends AbstractParticipationRule
{

    /**
     * Performs an authorization check.
     *
     * @param  array $params Any parameters passed as part of the route.
     * @param  User  $user   The user to authorize, if any. Null value assumes
     *                       that no user is logged in.
     *
     * @throws InvalidArgumentException If unable to locate a Participant instance in $params,
     *                                  or if the assessment isn't a due work
     * @throws Unauthorized If assessment is not self-assessable, there's no response, or user is not the participant
     * @return bool
     * @throws UnexpectedValueException
     */
    public function authorize(array $params = [], User $user = null)
    {
        // If there's no session, this test fails
        if (!$user instanceof User) {
            throw new Unauthorized();
        }

        $assessment  = $this->findAssessable($params);

        // Only due works may be self-assessed.
        // Task self-assessment is a different, and probably misnamed concept.
        // It allows participants to mark themselves as having completed a
        // task, which is outside the scope of this authorizer.
        if (!$assessment instanceof DueWork\Model) {
            throw new InvalidArgumentException("Only due work may be self-assessed");
        }

        $student     = $this->findStudent($params);
        // If a response has been submitted for self-assessment, we are guaranteed to find a participation record.
        $participant = $assessment->getParticipantIfExtant($student);

        // Check logged in user is the participant
        if (!$participant || $user->getId() != $participant->getUser()->getId()) {
            throw new Unauthorized();
        }

        // DueWork must allow either self-reflect or self-grade, and have a submitted response, to self-assess
        if (!$assessment->isSelfAssessable()) {
            throw new Unauthorized('', Error\Mapper::ERROR_NO_SELF_ASSESS);
        }

        // TODO - this is ambiguous. The response being self-assessed should be specified in the route args
        $mostRecentResponse = $participant->findMostRecentResponse();

        /* Error when there are no responses at all
         */
        if (!$mostRecentResponse) {
            throw new Unauthorized('', Error\Mapper::ERROR_COMPLETE_BEFORE_SELF_REFLECT);
        }

        // Error out if participant has no Draft responses to complete
        // Nothing to do in that case
        if ($mostRecentResponse->isSubmitted()) {
            throw new Unauthorized('', Error\Mapper::ERROR_COMPLETE_BEFORE_SELF_REFLECT);
        }

        // If a due work passes this gauntlet: it's self-assessable
        return true;
    }
}
