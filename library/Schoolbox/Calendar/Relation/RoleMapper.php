<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Calendar\Relation;

use Schoolbox\Mapper;

/**
 * Class RoleMapper
 *
 * Maintains relationships between Roles and Events via event_role
 * Historically, Roles and Groups were terminologies used interchangeably
 *
 * @package Schoolbox\Calendar\Relation
 */
class RoleMapper extends Mapper
{

    /**
     * Mapping
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('event_role');
        $this->hasModel(Role::class);

        $this->hasMap([
            'column' => 'event_id',
            'property' => 'eventId',
            'type' => self::TYPE_INTEGER,
        ]);

        $this->hasMap([
            'column' => 'role_id',
            'property' => 'roleId',
            'type' => self::TYPE_INTEGER,
        ]);
    }


    /**
     * No notifications for this model
     *
     * @param object $object
     * @param string $verb
     * @param null   $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        return true;
    }
}
