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
     * Controller constructor.
     *
     * @param Error\Mapper $errorMapper
     * @param Mapper       $attendanceMapper
     * @param UserMapper   $userMapper
     */
    public function __construct(
        Error\Mapper $errorMapper,
        Mapper $attendanceMapper,
        UserMapper $userMapper
    ) {
        $this->errorMapper = $errorMapper;
        $this->attendanceMapper = $attendanceMapper;
        $this->userMapper = $userMapper;
    }


    /**
     * Generate an html fragment containing the current list of attendees
     *
     * Ideally this would be managed in vue somehow, but we have to build a vue-compatible
     * toast that behaves synonymously with our jquery toast; plus, selectize and confirmDialog
     * wrangling is a thing we have yet to defeat
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
        $analysis = new PayloadAnalysis($event->getMaxAttendees(), $guests);

        $newGuests = explode(',', $this->request->post['guests'] ?? '');
        $oldGuests = array_column($guests, 'id');

        $ids = array_diff($newGuests, $oldGuests);

        /* Added a user who's already in the list
         *
         * Regenerate a new html fragment for the people currently invited
         * (another user, other than you, might have invited them and they don't appear in the list atm)
         */
        if (empty($ids)) {
            $this->getCurrent($event);
            return;
        }

        /* Don't exceed the maximum number of guests, if one was specified
         */
        if ($analysis->getNumAvailable() <= 0) {
            throw $this->errorMapper->generateException(Error\Mapper::ERROR_EVENT_ATTENDANCE_MAX_REACHED);
        }

        foreach ($ids as $id) {
            $this->attendanceMapper->save((new Event\Attendance\Model())
                ->setEvent($event)
                ->setUser($this->userMapper->createModel()->setId($id))
                ->setStatus(Event\Attendance\Model::STATUS_PENDING));
        }

        /* Regenerate a new html fragment for the people currently invited
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
            'user_id' => $this->request->post['userId']
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
        /* Check if they're already on the list - if so, shank down their existing record
         * Otherwise, spawn a new record for them if the maximum guests haven't been reached
         */
        try {
            $attendance = $this->attendanceMapper->findOneByColumnList([
                'event_id' => $event->getId(),
                'user_id'  => $this->getUser()->getId()
            ]);
        } catch (MapperException $e) {

            /* Check if there are any free slots; if not, throw the usual message
             */
            if ($event->getMaxAttendees() !== null) {
                $numGuests = $this->attendanceMapper->countByColumnList([
                    'event_id' => $event->getId(),
                    'status' => [Model::STATUS_ACCEPTED]
                ]);

                if ($numGuests >= $event->getMaxAttendees()) {
                    throw $this->errorMapper->generateException(Error\Mapper::ERROR_EVENT_ATTENDANCE_MAX_REACHED);
                }
            }

            $attendance = $this->attendanceMapper->createModel()
                ->setEvent($event)
                ->setUser($this->getUser());
        }

        /* Then update its status and user details if necessary
         */
        $attendance->setStatus(Model::STATUS_ACCEPTED);

        $this->attendanceMapper->save($attendance);

        /* Regenerate a new html fragment for the people currently invited
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

        /* Regenerate a new html fragment for the people currently invited
         */
        $this->getCurrent($event);
    }
}
