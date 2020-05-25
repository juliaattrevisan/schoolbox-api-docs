<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar\Event;

use Alaress\Web\Controller\Base;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\Access as FolderAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\RoleMapper;

/**
 * Class Controller
 *
 * @package Schoolbox\Calendar\Event
 */
class Controller extends Base
{

    /**
     * @var ErrorMapper
     */
    private $errorMapper;


    /**
     * @var RoleMapper
     */
    private $roleMapper;


    /**
     * @var FolderMapper
     */
    private $folderMapper;


    /**
     * @var EventMapper
     */
    private $eventMapper;


    /**
     * Controller constructor.
     *
     * @param ErrorMapper  $errorMapper
     * @param RoleMapper   $roleMapper
     * @param FolderMapper $folderMapper
     * @param EventMapper  $eventMapper
     */
    public function __construct(ErrorMapper $errorMapper, RoleMapper $roleMapper, FolderMapper $folderMapper, EventMapper $eventMapper)
    {
        $this->errorMapper  = $errorMapper;
        $this->roleMapper   = $roleMapper;
        $this->folderMapper = $folderMapper;
        $this->eventMapper  = $eventMapper;
    }


    /**
     * View an event's detail screen
     *
     * @param Event $event
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getEvent(Event $event): void
    {
        $calendar = new \Calendar();
        $helper = new Helper($this->roleMapper, $this->folderMapper);

        $attendanceStatus = $calendar->getAttendanceByEventAndUser($event->getId(), $this->getUser()->getId());

        $this->getResponse()->content->set($this->buildHtmlOutput('@calendarEvent/event.twig', [
            'event'            => $event,
            'level'            => $helper->getEventLevel($event),
            'canModify'        => $calendar->getPerm($event->getId()) >= UserAccess::ACCESS_WRITE,
            'campusCollection' => $helper->getCampusCollection($event),
            'roleCollection'   => $helper->getRoleCollection($event),
            'groupCollection'  => $helper->getGroupCollection($event),
            'attendance'       => [
                'status'       => $attendanceStatus,
                'nextAction'   => ($attendanceStatus) ? 'remove' : 'add',
            ],
        ])->setTitle($event->getTitle()));
    }


    /**
     * Modify an event's settings
     *
     * @param Event $event
     * @throws \Exception
     */
    public function getEventModify(Event $event): void
    {

        /* Default values
         */
        $defaultTypeId      = $event->getType()->getId();
        $defaultAccessLevel = $event->getAccess();

        /* Preselected campuses
         */
        $selectedCampuses = [];
        $allCampuses      = $event->getCampusCollection();

        foreach ($allCampuses as $currentCampus) {
            $selectedCampuses[] = $currentCampus->getId();
        }

        /* Preselected roles
         */
        $selectedRoles = [];
        $allRoles      = $event->getRoleCollection();

        foreach ($allRoles as $currentRole) {
            $selectedRoles[] = $currentRole->getRoleId();
        }

        /* Only provide publish date if one was provided and if it
         * wasn't useless somehow
         */
        if ($event->getPublishDateTime() && strlen($event->getPublishDateTime()->getDatabase())) {
            $publishDate = $event->getPublishDateTime()->getLocal();
        } else {
            $publishDate = '';
        }
    }
}
