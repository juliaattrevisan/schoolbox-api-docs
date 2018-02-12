<?php
/**
 * IsParentOfStudent - Determine if user is parent of the student in the record we are viewing
 *
 * @deprecated Use `IsParentOfUser` rule instead
 *
 * @author      Damian Sloane <steve.chan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Authorizer\Rule;

use Schoolbox\User;
use Alaress\Authorizer\Failure\Unauthorized;

class IsParentOfStudent implements IAuthorizationRule
{
    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null)
    {
        $params['student']->loadParents();

        if ($user && $user->isParentOf($params['student'])) {
            return true;
        }

        throw new Unauthorized();
    }
}
