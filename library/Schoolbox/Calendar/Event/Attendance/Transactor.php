<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar\Event\Attendance;

use PDOException;
use Schoolbox\Calendar\Event;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use UnexpectedValueException;

/**
 * Class Transactor
 *
 * Saves attendances for a particular event.
 *
 * @package Schoolbox\Calendar\Event\Attendance
 */
class Transactor
{

    /**
     * @var Mapper
     */
    private $attendanceMapper;


    /**
     * @var UserMapper
     */
    private $userMapper;


    /**
     * Controller constructor.
     *
     * @param Mapper      $attendanceMapper
     * @param UserMapper  $userMapper
     */
    public function __construct(
        Mapper $attendanceMapper,
        UserMapper $userMapper
    ) {
        $this->attendanceMapper = $attendanceMapper;
        $this->userMapper = $userMapper;
    }


    /**
     * Update a diff of event attendees
     *
     * Keep the intersect of who was previously invited and who is currently invited
     * thus preserving their current response setting
     *
     * Trim away everyone not in this set
     *
     * @param Event $event
     * @param array $guestIds
     * @throws MapperException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function update(Event $event, array $guestIds): void
    {

        /* No guests to process; just leave early
         */
        if (empty($guestIds)) {
            return;
        }

        /* Sanitize values for now
         * You'd have to iteratively create placeholders into the query, otherwise
         */
        $ids = implode(',', array_map('intval', $guestIds));

        $query = "
            DELETE FROM {$this->attendanceMapper->getTable()}
            WHERE event_id = :eventId
            AND user_id NOT IN ({$ids})
        ";

        $this->attendanceMapper->getDatabase()->query($query, [
            'eventId' => $event->getId(),
        ]);

        $existingUsers = $this->attendanceMapper->findByColumn('event_id', $event->getId())->getPropertyValues('user');
        $existingIds = (new Collection($existingUsers))->indexBy('id')->keys();

        foreach ($guestIds as $currentGuest) {
            if (!in_array($currentGuest, $existingIds, false)) {
                $this->attendanceMapper->save((new Event\Attendance\Model())
                    ->setEvent($event)
                    ->setUser($this->userMapper->createModel()->setId($currentGuest))
                    ->setStatus(Event\Attendance\Model::STATUS_PENDING));
            }
        }
    }
}
