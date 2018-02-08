<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\User;
use InvalidArgumentException;

/**
 * Checks the user is a participant, and has no non-draft interactions
 */
class ParticipantHasNoInteractions extends AbstractParticipationRule
{

    /**
     * Only allow if participant has no submitted responses and no published feedback
     *
     * @inheritdoc
     * @throws InvalidArgumentException If unable to find a Participant object in route params
     * @throws Unauthorized If Participant has any non-draft responses or feedback
     */
    public function authorize(array $params = [], User $user = null)
    {
        $assessable  = $this->findAssessable($params);
        $student     = $this->findStudent($params);
        $participant = $assessable->getParticipantIfExtant($student);

        if ($participant && ($participant->hasSubmittedResponse() || $participant->hasPublishedFeedback())) {
            throw new Unauthorized();
        }
        return true;
    }
}
