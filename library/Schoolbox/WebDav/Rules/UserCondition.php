<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\WebDav\Rules;

use Alaress\Rules\Condition;
use Schoolbox\User;

class UserCondition extends Condition\In {

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
    function assertIn($user)
    {
//        printf("ASSERT: %s.%s IN (%s)\n", $user->getUsername(), $this->field, implode(', ', $this->validValues));

        if (strpos($this->field, '.') !== false) {
            $path = explode('.', $this->field);
        } else {
            $path = array($this->field);
        }

        // could be something like role.type or defaultCampus.id
        // will be cast to $user->getRole()->getType()
        $value  = $user;
        $string = 'User';

        foreach ($path as $get) {
            $string .= '->get'.ucfirst($get).'()';
            $method = array($value, "get$get"); // methods aren't case sensitive......
            if (!is_callable($method)) {
                throw new \DomainException("Failed to call $string, method does not exist");
            }
            $value = call_user_func($method);
        }

        return in_array($value, $this->validValues);
    }

}
