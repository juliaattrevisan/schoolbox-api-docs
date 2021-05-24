<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\WebDav\Rules;

use Alaress\ObjectReader;
use Alaress\Rules\Condition;
use Schoolbox\User;

class UserCondition extends Condition\In
{

    /**
     * @param string           $field
     * @param array|string|int $validValues
     * @param bool             $negate
     */
    public function __construct($field, $validValues, $negate = false)
    {
        parent::__construct($field, $validValues, $negate);
    }


    /**
     * @param User $user
     * @return bool
     */
    public function assertIn($user)
    {
//        printf("ASSERT: %s.%s IN (%s)\n", $user->getUsername(), $this->field, implode(', ', $this->validValues));

        $reader = new ObjectReader();
        $value  = $reader->read($user, $this->field);

        return in_array($value, $this->validValues);
    }


    /**
     * @return string
     */
    public function getSummary()
    {
        $pattern = count($this->validValues) == 1 ? "user.%s %s= %s" : "user.%s %sIN (%s)";

        return sprintf($pattern, $this->field, $this->isNegated() ? 'NOT ' : '', implode(', ', $this->getValidValues()));
    }
}
