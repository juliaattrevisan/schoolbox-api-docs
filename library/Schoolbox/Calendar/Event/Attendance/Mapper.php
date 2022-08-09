<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Event\Attendance;

use PDO;
use PDOStatement;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;
use Schoolbox\UserMapper;
use UnexpectedValueException;

/**
 * Class Mapper
 *
 * @package Schoolbox\Calendar\Event\Attendance
 *
 * @method Model[]|Collection findByColumn($columnName, $columnValue, $options = array())
 * @method Model findOneByColumnList($columnList, array $options = array())
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('event_attendance');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('event_id', EventMapper::class, 'id', self::TYPE_MODEL, null, 'event', 'event');
        $this->joins('user_id', UserMapper::class, 'id', self::TYPE_MODEL, null, 'user', 'user');
        $this->uses('status', self::TYPE_INTEGER);
    }


    /**
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('event', 'e')},
                   {$this->getJoinColumnString('user', 'u')}
        ";
    }


    /**
     * @return string
     * @throws DefinitionException
     */
    protected function getFromString()
    {
        return "
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('event', '', 'e')}
            INNER JOIN {$this->getJoinSql('user', '', 'u')}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection|Model[]
     * @throws DefinitionException
     * @throws Exception
     * @throws UnexpectedValueException
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {

            /** @var User $user */
            $user = $this->getJoinMapper('user')->hydrate($row, 'u');

            /** @var Event $event */
            $event = $this->getJoinMapper('event')->hydrate($row, 'e');

            /** @var Model $attendance */
            $attendance = $this->hydrate($row);

            $attendance->setEvent($event)->setUser($user);

            $collection->add($attendance);
        }

        return $collection;
    }


    /**
     * Find a list of attendees, sorted by lastname then by firstname, for a particular event
     * Returns as a set of key-value pairs of (id, name, status) in said order
     *
     * @param Event $event
     * @return array
     */
    public function findAttendances(Event $event): array
    {
        $pairs = [];

        $attendances = $this->findByColumn('event_id', $event->getId(), [
            'cache' => false,
            'order' => [
                'u.lastname' => 'asc',
                'u.given_name' => 'asc'
            ]
        ]);

        foreach ($attendances as $attendance) {
            $pairs[] = [
                'id' => $attendance->getUser()->getId(),
                'name' => $attendance->getUser()->getFullname(),
                'status' => $attendance->getStatus()
            ];
        }

        return $pairs;
    }

    /**
     * Taken care by EventRecurrenceMapper
     * @inheritDoc
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        return true;
    }
}
