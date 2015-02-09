<?php
/**
 * RoleMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Calendar\Relation;

use Schoolbox;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper;

class RoleMapper extends Schoolbox\External\Mapper
{

    /**
     * Mapping
     *
     * @return Bool|void
     */
    protected function mapping()
    {
        $this->hasTable('event_group');
        $this->hasModel('\\Schoolbox\\Calendar\\Relation\\Role');

        $this->hasMap(
            array(
                'column' => 'event_id',
                'property' => 'eventId',
                'type' => self::TYPE_INTEGER,
            )
        );
        $this->hasMap(
            array(
                'column' => 'group_id',
                'property' => 'roleId',
                'type' => self::TYPE_INTEGER,
            )
        );
    }

    /**
     * Purge existing sid assignations
     *
     * @param Schoolbox\Calendar\Event $event
     */
    public function cleanse(Schoolbox\Calendar\Event $event)
    {
        $statement = $this->database->prepare(
            "
			DELETE FROM `{$this->table}`
			WHERE `event_id` = :identifierValue
		"
        );

        $statement->bindValue(':identifierValue', $event->getId());
        $statement->execute();
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