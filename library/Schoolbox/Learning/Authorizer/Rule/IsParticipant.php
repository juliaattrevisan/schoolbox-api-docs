<?php
/**
 * isParticipant - Determine if a user can submit to this assessment and view results
 *
 * @author      Damian Sloane <steve.chan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Alaress\Authorizer\Failure\Unauthorized;

class IsParticipant implements IAuthorizationRule
{
    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null)
    {

        /* Block students seeing other students' outcomes */
        if ($params['participant'] instanceof Participant) {
            if ($user->getId() == $params['participant']->getUser()->getId()) {
                return true;
            }
        }

        throw new Unauthorized();
    }
}
