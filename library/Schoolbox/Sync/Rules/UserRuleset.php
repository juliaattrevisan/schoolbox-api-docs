<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Rules;


use Schoolbox\CampusMapper;
use Schoolbox\User;

/**
 * Class UserRuleset
 *
 * @package Schoolbox\Sync\Rules
 * @method User applyTo($input, User $previous = null)
 */
class UserRuleset extends Ruleset
{

    /**
     * Method override to hook the value and do some user-specific things
     *
     * @param User   $user
     * @param string $property
     * @param mixed  $value
     */
    protected function applyModelPropertyValue($user, $property, $value)
    {
        // TODO I honestly can't think of any other way to deal with a derived field like this
        if ($property == 'defaultCampus') {
            // cast to Campus object
            $campus = CampusMapper::getInstance()->createModel()->setId($value);

            $campusList = $this->mapper->getModelProperty($user, 'campusCollection');

            if (!in_array($value, $campusList)) {
                $campusList[] = $value;
            }

            $this->mapper->setModelProperty($user, 'campusCollection', $campusList);

            $value = $campus;
        }

        parent::applyModelPropertyValue($user, $property, $value);
    }

} 