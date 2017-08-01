<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\User;

class ParticipantHasNoInteractions implements IAuthorizationRule
{

    /**
     * Only allow if participant has no submitted responses and no published feedback
     *
     * @inheritdoc
     * @throws \InvalidArgumentException If unable to find a Participant object in route params
     * @throws Unauthorized If Participant has any non-draft responses or feedback
     */
    public function authorize(array $params = [], User $user = null)
    {
        $participant = $this->findParticipant($params);
        if ($participant->hasSubmittedResponse() || $participant->hasPublishedFeedback()) {
            throw new Unauthorized();
        }
        return true;
    }


    /**
     * @param array $params
     * @return Participant
     * @throws \InvalidArgumentException If unable to find a Participant object in route params
     */
    private function findParticipant(array $params)
    {
        if (isset($params['participant']) && $params['participant'] instanceof Participant) {
            return $params['participant'];
        }
        throw new \InvalidArgumentException('Unable to find a Participant object in route params');
    }
}
