<?php
/**
 * IsOwner - Determine if a pastoral record is owned by the logged in user
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

class IsOwner implements IAuthorizationRule
{
    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null)
    {
        // If there is no record then we have to assume they're creating a new one

        if (!isset($params['record'])) {
            return true;
        }

        if ($params['record']->getOwner()->getId() == $user->getId()) {
            return true;
        }

        // BZZZZZT! You're outta here.

        throw new Unauthorized();
    }
}