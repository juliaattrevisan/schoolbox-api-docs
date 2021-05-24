<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Calendar\Relation;

use Schoolbox;
use Schoolbox\Mapper;

/**
 * Class PersonalMapper
 *
 * Maintains relationships between Users and Events via event_user
 * These are a user's personal events
 *
 * @package Schoolbox\Calendar\Relation
 */
class PersonalMapper extends Schoolbox\Mapper
{

    /**
     * Mapping
     *
     * @return void
     * @throws Mapper\DefinitionException
     */
    protected function mapping()
    {
        $this->hasTable('event_user');
        $this->hasModel(Personal::class);

        $this->hasMap(
            array(
                'column' => 'event_id',
                'property' => 'eventId',
                'type' => self::TYPE_INTEGER,
            )
        );
        $this->hasMap(
            array(
                'column' => 'user_id',
                'property' => 'userId',
                'type' => self::TYPE_INTEGER,
            )
        );
        $this->hasJoin(
            'personal',
            array(
                'mapper' => '\\Schoolbox\\UserMapper',
                'this_key' => 'user_id',
                'other_key' => 'id',
            )
        );
    }


    /**
     * This mapper doesn't raise notifications - the event mapper handles that.
     *
     * @param object $object
     * @param string $verb
     * @param null   $previous
     *
     * @return true
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        return true;
    }
}
