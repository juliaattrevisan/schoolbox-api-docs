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
use DomainException;
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
     * @throws ServiceNotFound
     */
    public function getCurrent(Event $event): void
    {
        $data = (new ListPayload($this->di, $this->attendanceMapper))->generate($event);

        $this->getResponse()->content->set($this->buildJsonOutput($data));
    }


    /**
     * Add attendees to the list
     *
     * @param Event $event
     * @throws MapperException
     * @throws PDOException
     * @throws ServiceNotFound
     * @throws UnexpectedValueException
     * @throws Exception
     */
    public function postCreate(Event $event): void
    {

        /* Don't exceed the maximum number of guests, if one was specified
         */
        $guests = $this->attendanceMapper->findAttendances($event);

        if ($event->getMaxAttendees() && \count($guests) >= $event->getMaxAttendees()) {
            throw $this->errorMapper->generateException(Error\Mapper::ERROR_EVENT_ATTENDANCE_MAX_REACHED);
        }

        $newGuests = explode(',', $this->request->post['guests'] ?? '');
        $oldGuests = array_column($guests, 'id');

        $ids = array_diff($newGuests, $oldGuests);

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
     * @throws ServiceNotFound
     */
    public function postDelete(Event $event): void
    {
        $this->attendanceMapper->deleteByColumnList([
            'event_id' => $event->getId(),
            'user_id' => $this->request->post['guest']
        ]);

        /* Regenerate a new html fragment for the people currently invited
         */
        $this->getCurrent($event);
    }
}
