<?php
/**
 *
 * @author      Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\Forbidden;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\User;
use InvalidArgumentException;

/**
 * Class CanSelfAssess
 *
 * In $params we need:
 *  - 'participant' Participant:
 *      - assessable fully hydrated
 *
 * The rule is:
 *  - if the assessment is self assessable and you are the participant, you are good
 *
 *
 * @package Schoolbox\Learning\Authorizer\Rule
 */
class CanSelfAssess implements IAuthorizationRule
{
    /**
     * @inheritdoc
     * @throws \InvalidArgumentException If unable to locate a Participant instance in $params
     */
    public function authorize(array $params = [], User $user = null)
    {
        // Grab the participant and assessment from $params
        if (isset($params['participant']) && $params['participant'] instanceof Participant) {
            /** @var Participant $participant */
            $participant = $params['participant'];

            /** @var IAssessable $assessment */
            $assessment = $participant->getAssessable();
        } else {
            throw new InvalidArgumentException("Cannot check auth, need a participant param of 'participant'");
        }

        // Error out if assessment is not self-assessable
        if (1 != 1) { // TODO: replace with "if (!$assessment->isSelfAssessable())" once it exists
            throw new \Error('Assessment is not self-assessable'); // TODO: use an error from DB
        }

        // Check logged in user is the participant
        if ($user->getId() == $participant->getUser()->getId()) {
            // Assessment is self-assessable and logged in user is the participant, good to go
            return true;
        }

        throw new Forbidden();
    }
}