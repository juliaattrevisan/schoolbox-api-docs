<?php
/**
 * Validates whether the attribute provided is true
 * This is generally used to check if a config variable is true
 *
 * @author    James Leckie james.leckie@alaress.com.au
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\User;

class IsTrue implements IAuthorizationRule
{
    /**
     * The value being tested for truth
     *
     * @var string
     */
    protected $value;

    /**
     * Constructor (obviously)
     *
     * @param string $paramsKey
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null)
    {
        if ($this->value == true) {
            return true;
        }
        throw new Unauthorized();
    }
}
