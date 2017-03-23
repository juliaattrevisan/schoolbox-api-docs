<?php
/**
 * Determine if a pastoral comment is owned by the logged in user
 *
 * @author      Damian Sloane <steve.chan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Authorizer\Rule;

use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Alaress\Authorizer\Failure\Unauthorized;

class IsCommentOwner implements IAuthorizationRule
{
    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null)
    {
        if ($params['comment']->getOwner()->getId() == $user->getId()) {
            return true;
        }

        throw new Unauthorized();
    }
}