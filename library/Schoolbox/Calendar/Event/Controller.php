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
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Permission\Access\UserAccess;
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
     * @var EventTypeMapper
     */
    private $eventTypeMapper;


    /**
     * Controller constructor.
     *
     * @param RoleMapper      $roleMapper
     * @param FolderMapper    $folderMapper
     * @param EventMapper     $eventMapper
     * @param EventTypeMapper $eventTypeMapper
     */
    public function __construct(RoleMapper $roleMapper, FolderMapper $folderMapper, EventMapper $eventMapper, EventTypeMapper $eventTypeMapper)
    {
        $this->roleMapper   = $roleMapper;
        $this->folderMapper = $folderMapper;
        $this->eventMapper  = $eventMapper;
        $this->eventTypeMapper = $eventTypeMapper;
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

        /* Recurrence
         */
        $recurrence = [];

        if ($event->getSeriesId()) {
            $eventRecurrenceMapper = new \Schoolbox\Calendar\EventRecurrenceMapper($this->eventMapper);
            $recurrence            = $eventRecurrenceMapper->getSeriesRecurrence($event);
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@calendarEvent/create.twig', [
            'event' => $event,
            'action' => 'Update',
            'eventTypes' => $this->eventTypeMapper->findAll(),
            'recurrence' => $recurrence,
            'personalLevel' => Event::ACCESS_PERSONAL,
            'redirect' => $event->setupRedirect(
                urldecode($this->getRequest()->query['returnTo']),
                $event->getStartDateTime()->getURLDate(),
                $this->getRequest()->query['homepageId'],
                $this->getRequest()->query['userId']
            ),
            'returnStr' => '&returnTo=' . $this->getRequest()->query['returnTo'] .
                ($this->getRequest()->query['userId'] ? '&userId=' . $this->getRequest()->query['userId'] : '') .
                ($this->getRequest()->query['homepageId'] ? '&homepageId=' . $this->getRequest()->query['homepageId'] : '') .
                ($this->getRequest()->query['returnDate'] ? '&returnDate=' . $this->getRequest()->query['returnDate'] : ''),
        ])->setTitle("Modify {$event->getTitle()}"));
    }
}
