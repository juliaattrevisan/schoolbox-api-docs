<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar\Event;

use Alaress\Web\Controller\Base;
use Schoolbox\Calendar\Event;
use Schoolbox\Campus;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\RoleMapper;

class Controller extends Base
{
    /**
     * @var RoleMapper
     */
    protected $roleMapper;

    /**
     * Controller constructor.
     * @param RoleMapper $roleMapper
     */
    public function __construct(RoleMapper $roleMapper)
    {
        $this->roleMapper = $roleMapper;
    }

    /**
     * @param Event $event
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getEvent(Event $event)
    {
        $calendar = new \Calendar();

        $attendanceStatus = $calendar->getAttendanceByEventAndUser($event->getId(), $this->getUser()->getId());

        $this->getResponse()->content->set($this->buildHtmlOutput('@calendarEvent/event.twig', [
            'event' => $event,
            'campusCollection'  => $this->getCampusCollection($event),
            'roleCollection'    => $this->getRoleCollection($event),
            'attendance'        => [
                'status'            => $attendanceStatus,
                'nextAction'        => ($attendanceStatus) ? 'remove' : 'add',
            ],
            'canModify'         => $calendar->getPerm($event->getId()) >= UserAccess::ACCESS_WRITE
        ])->setTitle($event->getTitle()));
    }

    /**
     * @param Event $event
     * @return array
     */
    private function getCampusCollection(Event $event): array
    {
        $result = [];

        /** @var Campus $campus */
        foreach ($event->getCampusCollection()->getAll() as $campus) {
            $result[] = [
                'id' => $campus->getId(),
                'name' => $campus->getName(),
                'extTimetableCode' => $campus->getExtTimetableCode(),
                'timetableDay' => $campus->getTimetableDay(),
            ];
        }

        return $result;
    }

    /**
     * @param Event $event
     * @return array
     */
    private function getRoleCollection(Event $event): array
    {
        $result = [];

        /** @var \Schoolbox\Calendar\Relation\Role $role */
        foreach ($event->getRoleCollection()->getAll() as $role) {
            try {
                $result[] = $this->roleMapper->findById($role->getRoleId());
            } catch (Exception $e) {
                // noop
            }
        }

        return $result;
    }
}
