<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar\Event;

use Alaress\Web\Controller\Base;
use Schoolbox\Calendar\Event;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\RoleMapper;

class Controller extends Base
{
    /**
     * @var RoleMapper
     */
    protected $roleMapper;

    /**
     * @var FolderMapper
     */
    protected $folderMapper;

    /**
     * Controller constructor.
     * @param RoleMapper   $roleMapper
     * @param FolderMapper $folderMapper
     */
    public function __construct(RoleMapper $roleMapper, FolderMapper $folderMapper)
    {
        $this->roleMapper   = $roleMapper;
        $this->folderMapper = $folderMapper;
    }

    /**
     * @param Event $event
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getEvent(Event $event)
    {
        $calendar = new \Calendar();
        $helper = new Helper($this->roleMapper, $this->folderMapper);

        $attendanceStatus = $calendar->getAttendanceByEventAndUser($event->getId(), $this->getUser()->getId());

        $this->getResponse()->content->set($this->buildHtmlOutput('@calendarEvent/event.twig', [
            'event' => $event,
            'level' => $helper->getEventLevel($event),
            'campusCollection'  => $helper->getCampusCollection($event),
            'roleCollection'    => $helper->getRoleCollection($event),
            'groupCollection'   => $helper->getGroupCollection($event),
            'attendance'        => [
                'status'            => $attendanceStatus,
                'nextAction'        => ($attendanceStatus) ? 'remove' : 'add',
            ],
            'canModify'         => $calendar->getPerm($event->getId()) >= UserAccess::ACCESS_WRITE
        ])->setTitle($event->getTitle()));
    }
}
