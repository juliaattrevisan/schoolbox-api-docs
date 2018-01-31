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
    /** @var string */
    private $key;


    /**
     * @param string $paramKeyToCheck
     */
    public function __construct($paramKeyToCheck = 'student')
    {
        $this->key = $paramKeyToCheck;
    }


    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null)
    {
        $params[$this->key]->loadParents();

        if ($user->isParentOf($params[$this->key])) {
            return true;
        }

        throw new Unauthorized();
    }
}
