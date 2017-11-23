<?php
/**
 *
 * @author      Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWorkModel;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Task\Model as TaskModel;
use Schoolbox\User;
use InvalidArgumentException;

/**
 * Class CanSelfAssess
 *
 * In $params we need:
 *  - 'participant' Participant:
 *      - assessable fully hydrated, must be a task or due work
 *
 * The rule is:
 *  - if the due work or task is self assessable and you are the participant, you are good
 *
 *
 * @package Schoolbox\Learning\Authorizer\Rule
 */
class CanSelfAssess implements IAuthorizationRule
{
    /**
     * Performs an authorization check.
     *
     * @param  array $params Any parameters passed as part of the route.
     * @param  User  $user   The user to authorize, if any. Null value assumes
     *                       that no user is logged in.
     *
     * @throws InvalidArgumentException If unable to locate a Participant instance in $params, or assessment is not a due work or task
     * @throws Unauthorized If assessment is not self-assessable, there's no response, or user is not the participant
     * @return bool
     */
    public function authorize(array $params = [], User $user = null)
    {
        // If there's no session, this test fails
        if (!$user instanceof User) {
            throw new Unauthorized();
        }

        // Grab the participant and assessment from $params
        if (empty($params['participant']) || !$params['participant'] instanceof Participant) {
            throw new InvalidArgumentException("Cannot check auth, need a participant param of 'participant'");
        }

        /** @var Participant $participant */
        $participant = $params['participant'];
        $assessment = $participant->getAssessable();
        
        // Error out if assessment is not a due work or task
        if (!$assessment instanceof DueWorkModel && !$assessment instanceof TaskModel) {
            throw new InvalidArgumentException("Cannot check auth, assessment must be due work or task");
        }

        // Error out if assessment is not self-assessable
        if (!$assessment->isSelfAssessable()) {
            throw new Unauthorized('', ErrorMapper::ERROR_NO_SELF_ASSESS);
        }

        // Check logged in user is the participant
        if ($user->getId() != $participant->getUser()->getId()) {
            throw new Unauthorized();
        }

        // Error out if participant has no responses
        if (!$participant->hasSubmittedResponse()) {
            throw new Unauthorized('', ErrorMapper::ERROR_COMPLETE_BEFORE_SELF_ASSESS);
        }

        // Assessment is self-assessable and logged in user is the participant, good to go
        return true;
    }
}
