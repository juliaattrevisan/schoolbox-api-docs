<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Grammar;

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
     * @inheritdoc
     */
    protected function isCollection(string $property): bool
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
