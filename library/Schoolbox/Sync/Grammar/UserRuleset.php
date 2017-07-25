<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Grammar;


use Schoolbox\CampusMapper;
use Schoolbox\User;
use Schoolbox\Mapper;

/**
 * Class UserRuleset
 *
 * @package Schoolbox\Sync\Rules
 * @method User applyTo($input, User $previous = null)
 */
class UserRuleset extends Ruleset
{

    /**
     * Abstractify this check so subclasses can wrangle
     *
     * @param $property
     * @return bool
     */
    protected function isCollection($property)
    {
        $map = $this->mapper->getMap();
        $mapping = $map->getOffset($property);

        if (in_array($property, ['userAccessRead', 'userAccessWrite', 'userAccessRemove', 'campusCollectionRemove'])) {
            return true;
        }

        if ($mapping['type'] == Mapper::TYPE_COLLECTION) {
            return true;
        }

        return false;
    }
} 