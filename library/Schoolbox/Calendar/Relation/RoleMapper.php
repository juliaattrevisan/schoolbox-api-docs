<?php
/**
 * RoleMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Calendar\Relation;

use Schoolbox;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper;

class RoleMapper extends Schoolbox\Mapper
{

    /**
     * Mapping
     *
     * @return Bool|void
     */
    protected function mapping()
    {
        $this->hasTable('event_role');
        $this->hasModel(Role::class);

        $this->hasMap(
            array(
                'column' => 'event_id',
                'property' => 'eventId',
                'type' => self::TYPE_INTEGER,
            )
        );
        $this->hasMap(
            array(
                'column' => 'role_id',
                'property' => 'roleId',
                'type' => self::TYPE_INTEGER,
            )
        );
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
