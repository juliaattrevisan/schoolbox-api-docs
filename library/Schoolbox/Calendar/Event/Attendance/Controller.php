<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar\Event\Attendance;

use Alaress\Web\Controller\Base;
use Aura\Di\Exception\ServiceNotFound;
use PDOException;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use UnexpectedValueException;

class Controller extends Base
{

    /**
     * @var ErrorMapper
     */
    private $errorMapper;


    /**
     * @var EventMapper
     */
    private $eventMapper;


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
     * @param ErrorMapper $errorMapper
     * @param EventMapper $eventMapper
     * @param Mapper      $attendanceMapper
     * @param UserMapper  $userMapper
     */
    public function __construct(
        ErrorMapper $errorMapper,
        EventMapper $eventMapper,
        Mapper $attendanceMapper,
        UserMapper $userMapper
    ) {
        $this->errorMapper = $errorMapper;
        $this->eventMapper = $eventMapper;
        $this->attendanceMapper = $attendanceMapper;
        $this->userMapper = $userMapper;
    }


    /**
     * Provide form
     *
     * @param Event $event
     * @throws ServiceNotFound
     */
    public function getCurrent(Event $event): void
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@calendarEventAttendance/index.twig', [
            'event' => $event,
        ])->setTitle('Example'));
    }


    /**
     * Process the diff of attendees
     *
     * @param Event $event
     * @throws MapperException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function postModify(Event $event): void
    {

        /* Combine the two sets of guests: new, from the selectize; old, from some hidden inputs
         * That should allow us get a diff of people in one request
         */
        $newGuests = explode(',', $this->request->post['guests']);
        $oldGuests = $this->request->post['existingGuests'];

        $guestIds = array_unique(array_merge($newGuests, $oldGuests));

        $this->updateAttendances($event, $guestIds);
    }


    /**
     * Update a diff of event attendees
     *
     * @param Event $event
     * @param array $guestIds
     * @throws MapperException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function updateAttendances(Event $event, array $guestIds): void
    {

        /* Keep the intersect of who was previously invited and who is currently invited
         * thus preserving their current response setting
         *
         * Trim away everyone not in this set
         */
        $query = "
            DELETE FROM {$this->attendanceMapper->getTable()}
            WHERE event_id = :eventId
            AND user_id NOT IN (:guestIds)
        ";

        $this->attendanceMapper->getDatabase()->query($query, [
            'eventId' => $event->getId(),
            'guestIds' => implode(',', array_map('intval', $guestIds))
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
