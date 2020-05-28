<?php
/**
 * CampusMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Calendar\Relation;

use Schoolbox;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper;

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
     * Purge existing sid assignations
     *
     * @param Schoolbox\Calendar\Event $event
     * @throws \PDOException
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
     * Find all the campuses belonging to an event
     *
     * @param Schoolbox\Calendar\Event $event
     * @return Collection
     * @throws Exception
     * @throws Mapper\DefinitionException
     * @throws \PDOException
     */
    public function findAllForEvent(Schoolbox\Calendar\Event $event)
    {
        $campusMapper = $this->getJoinMapper('campus');

        $query = "
            SELECT {$campusMapper->getColumnString('c')}
            FROM `{$this->table}` ec
            LEFT JOIN {$this->getJoinSql('campus', 'ec', 'c')}
            WHERE ec.event_id = :eventId
        ";

        $statement = $this->database->prepare($query);
        $statement->bindValue(':eventId', $event->getId());
        $statement->execute();

        $allUsers = new Collection();

        while ($row = $statement->fetch()) {
            $allUsers->add($campusMapper->hydrate($row, 'c'));
        }

        return $allUsers;
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
