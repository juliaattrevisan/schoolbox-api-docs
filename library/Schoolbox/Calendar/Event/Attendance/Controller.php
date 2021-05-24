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
use InvalidArgumentException;
use LogicException;
use PDOException;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventRecurrenceMapper;
use Schoolbox\Error;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use UnexpectedValueException;

/**
 * Class Controller
 *
 * Manages the creation and deletion of attendance records for an event
 *
 * Most methods will return a list of attendees currently invited to a particular event
 * Vue should handle that at some point, but we have too many jquery assets to juggle atm
 *
 * @package Schoolbox\Calendar\Event\Attendance
 */
class Controller extends Base
{

    /**
     * @var Error\Mapper
     */
    private $errorMapper;


    /**
     * @var Mapper
     */
    private $attendanceMapper;


    /**
     * @var UserMapper
     */
    private $userMapper;


    /**
     * @var EventRecurrenceMapper
     */
    private $eventRecurrenceMapper;

    /**
     * Controller constructor.
     *
     * @param Error\Mapper          $errorMapper
     * @param Mapper                $attendanceMapper
     * @param UserMapper            $userMapper
     * @param EventRecurrenceMapper $eventRecurrenceMapper
     */
    public function __construct(
        Error\Mapper $errorMapper,
        Mapper $attendanceMapper,
        UserMapper $userMapper,
        EventRecurrenceMapper $eventRecurrenceMapper
    ) {
        $this->errorMapper           = $errorMapper;
        $this->attendanceMapper      = $attendanceMapper;
        $this->userMapper            = $userMapper;
        $this->eventRecurrenceMapper = $eventRecurrenceMapper;
    }


    /**
     * Generate a payload containing the current state of affairs
     *
     * @param Event $event
     * @throws MapperException
     * @throws ServiceNotFound
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    public function getCurrent(Event $event): void
    {
        $data = (new Payload($this->di, $this->attendanceMapper))->generate($event, $this->getUser());

        $this->getResponse()->content->set($this->buildJsonOutput($data));
    }


    /**
     * Add attendees to the list
     *
     * @param Event $event
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws MapperException
     * @throws PDOException
     * @throws ServiceNotFound
     * @throws UnexpectedValueException
     */
    public function postCreate(Event $event): void
    {
        $guests = $this->attendanceMapper->findAttendances($event);
        $oldGuestIds = array_column($guests, 'id');
        $analysis = new PayloadAnalysis($event->getMaxAttendees(), $guests);

        /* You can only create attendances if we let you
         */
        $ids = [];

        if ((new ValidCanInvite())->isSatisfiedBy($this->getUser(), (bool)$this->di->get('config')->get('calendar_student_can_invite'))) {
            $newGuestIds = explode(',', $this->request->post->get('guests') ?? '');

            $ids = array_diff($newGuestIds, $oldGuestIds);
        }

        /* Added a user who's already in the list
         *
         * Regenerate a new html fragment for the people currently invited
         * (another user, other than you, might have invited them and they don't appear in the list atm)
         */
        if (empty($ids)) {
            $this->getCurrent($event);
            return;
        }

        $previous = clone $event;
        $previous->setGuests($oldGuestIds);

        /* Don't exceed the maximum number of guests, if one was specified
         */
        if ($event->getMaxAttendees() !== null && $analysis->getNumAvailable() <= 0) {
            throw $this->errorMapper->generateException(Error\Mapper::ERROR_EVENT_ATTENDANCE_MAX_REACHED);
        }

        foreach ($ids as $id) {
            $this->attendanceMapper->save((new Event\Attendance\Model())
                ->setEvent($event)
                ->setUser($this->userMapper->createModel()->setId($id))
                ->setStatus(Event\Attendance\Model::STATUS_PENDING));
        }

        $this->eventRecurrenceMapper->raiseNotification($event, \Schoolbox\Mapper::METHOD_UPDATE, $previous);

        /* Regenerate a new payload for the people currently invited
         */
        $this->getCurrent($event);
    }


    /**
     * Remove attendees from the list
     *
     * @param Event $event
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws MapperException
     * @throws ServiceNotFound
     * @throws UnexpectedValueException
     */
    public function postDelete(Event $event): void
    {
        $this->attendanceMapper->deleteByColumnList([
            'event_id' => $event->getId(),
            'user_id'  => $this->request->post->get('userId'),
        ]);

        /* Regenerate a new html fragment for the people currently invited
         */
        $this->getCurrent($event);
    }


    /**
     * User accepts invitation to an event
     *
     * @param Event $event
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws MapperException
     * @throws PDOException
     * @throws ServiceNotFound
     * @throws UnexpectedValueException
     */
    public function postAccept(Event $event): void
    {
        $guests = $this->attendanceMapper->findAttendances($event);
        $analysis = new PayloadAnalysis($event->getMaxAttendees(), $guests);

        /* Don't exceed the maximum number of guests, if one was specified
         */
        if ($event->getMaxAttendees() !== null && $analysis->getNumAvailable() <= 0) {
            throw $this->errorMapper->generateException(Error\Mapper::ERROR_EVENT_ATTENDANCE_MAX_REACHED);
        }

        /* Check if they're already on the list - if so, shank down their existing record
         * Otherwise, spawn a new record for them if the maximum guests haven't been reached
         */
        try {
            $attendance = $this->attendanceMapper->findOneByColumnList([
                'event_id' => $event->getId(),
                'user_id'  => $this->getUser()->getId()
            ]);
        } catch (MapperException $e) {
            $attendance = $this->attendanceMapper->createModel()
                ->setEvent($event)
                ->setUser($this->getUser());
        }

        /* Then update its status and user details if necessary
         */
        $attendance->setStatus(Model::STATUS_ACCEPTED);

        $this->attendanceMapper->save($attendance);

        /* Regenerate a new payload for the people currently invited
         */
        $this->getCurrent($event);
    }


    /**
     * User declines invitation to an event
     *
     * @param Event $event
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws MapperException
     * @throws PDOException
     * @throws ServiceNotFound
     * @throws UnexpectedValueException
     */
    public function postDecline(Event $event): void
    {
        /* Check if they're already on the list - if so, shank down their existing record
         * Otherwise exit early - no changes required
         */
        try {
            $attendance = $this->attendanceMapper->findOneByColumnList([
                'event_id' => $event->getId(),
                'user_id'  => $this->getUser()->getId()
            ]);

            /* Then update its status
             */
            $attendance->setStatus(Model::STATUS_DECLINED);

            $this->attendanceMapper->save($attendance);
        } catch (MapperException $e) {
            // noop
        }

        /* Regenerate a new payload for the people currently invited
         */
        $this->getCurrent($event);
    }
}
