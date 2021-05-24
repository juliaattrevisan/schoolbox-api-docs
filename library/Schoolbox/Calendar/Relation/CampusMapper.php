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
 * Class CampusMapper
 *
 * Maintains relationships between Campuses and Events via event_campus
 * These are a School level's events
 *
 * @package Schoolbox\Calendar\Relation
 */
class CampusMapper extends Schoolbox\Mapper
{

    /**
     * Mapping
     *
     * @return void
     * @throws Mapper\DefinitionException
     */
    protected function mapping()
    {
        $this->hasTable('event_campus');
        $this->hasModel(Campus::class);

        $this->hasMap(
            array(
                'column' => 'event_id',
                'property' => 'eventId',
                'type' => self::TYPE_INTEGER,
            )
        );
        $this->hasMap(
            array(
                'column' => 'campus_id',
                'property' => 'campusId',
                'type' => self::TYPE_INTEGER,
            )
        );
        $this->hasJoin(
            'campus',
            array(
                'mapper' => '\\Schoolbox\\CampusMapper',
                'this_key' => 'campus_id',
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
