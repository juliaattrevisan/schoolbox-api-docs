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
use Schoolbox\UserMapper;

class UserInCondition extends Condition\In {

    /**
     * @param string $field
     * @param array  $validValues
     * @param bool   $negate
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

        switch ($this->field) {
            case 'roletype':
                $value = $user->getRole()->getType();
                break;
            case 'defaultCampus':
                $value = $user->getDefaultCampus()->getId();
                break;
            default:
                // cast $field to :input->get$Field();
                $getField = UserMapper::getInstance()->getGetMethodName($this->field);
                if (method_exists($user, $this->field)) {
                    $value = call_user_func(array($user, $getField));
                    $value = is_object($value) ? $value->getId() : $value;
                } else {
                    $value = '';
                }
                break;
        }
        return in_array($value, $this->validValues);
    }

}
