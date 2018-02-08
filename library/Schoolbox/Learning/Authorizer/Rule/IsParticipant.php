<?php
/**
 * isParticipant - Determine if a user can submit to this assessment and view results
 *
 * @author      Damian Sloane <steve.chan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Schoolbox\User;
use Alaress\Authorizer\Failure\Unauthorized;
use InvalidArgumentException;

class IsParticipant extends AbstractParticipationRule
{

    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = [], User $user = null)
    {
        if (!$user) {
            throw new InvalidArgumentException('No user found to authorize');
        }
        if ($user->getId() == $this->findStudent($params)->getId()) {
            return true;
        }
        throw new Unauthorized();
    }
}
