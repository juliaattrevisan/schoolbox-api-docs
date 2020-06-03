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
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use UnexpectedValueException;

/**
 * Class Controller
 *
 * @package Schoolbox\Calendar\Event\Attendance
 */
class Controller extends Base
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
     * Find the ids of attendees currently for $event
     *
     * @param Event $event
     * @return array
     */
    private function findExistingIds(Event $event): array
    {
        return array_column($this->attendanceMapper->findAttendances($event), 'id');
    }


    /**
     * Add attendees to the list
     *
     * @param Event $event
     * @throws MapperException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function postCreate(Event $event): void
    {
        $newGuests = explode(',', $this->request->post['guests'] ?? '');
        $oldGuests = $this->findExistingIds($event);

        $ids = array_diff($newGuests, $oldGuests);

        foreach ($ids as $id) {
            $this->attendanceMapper->save((new Event\Attendance\Model())
                ->setEvent($event)
                ->setUser($this->userMapper->createModel()->setId($id))
                ->setStatus(Event\Attendance\Model::STATUS_PENDING));
        }
    }


    /**
     * Remove attendees from the list
     *
     * @param Event $event
     */
    public function postDelete(Event $event): void
    {
        $this->attendanceMapper->deleteByColumnList([
            'event_id' => $event->getId(),
            'user_id' => $this->request->post['guest']
        ]);
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
        $newGuests = explode(',', $this->request->post['guests'] ?? '');
        $oldGuests = $this->findExistingIds($event);

        $guestIds = array_unique(array_merge($newGuests, $oldGuests));

        (new Transactor($this->attendanceMapper, $this->userMapper))->update($event, $guestIds);
    }
}
