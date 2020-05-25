<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar\Event;

use Alaress\Web\Controller\Base;
use Aura\Di\Exception\ServiceNotFound;
use InvalidArgumentException;
use LogicException;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventLevelCollection;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\EventRecurrenceMapper;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\Access as FolderAccess;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use UnexpectedValueException;


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
     * @var Folder\Mapper
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
     * @var CampusMapper
     */
    private $campusMapper;


    /**
     * Controller constructor.
     *
     * @param RoleMapper      $roleMapper
     * @param Folder\Mapper   $folderMapper
     * @param EventMapper     $eventMapper
     * @param EventTypeMapper $eventTypeMapper
     * @param CampusMapper    $campusMapper
     */
    public function __construct(RoleMapper $roleMapper, Folder\Mapper $folderMapper, EventMapper $eventMapper, EventTypeMapper $eventTypeMapper, CampusMapper $campusMapper)
    {
        $this->roleMapper = $roleMapper;
        $this->folderMapper = $folderMapper;
        $this->eventMapper = $eventMapper;
        $this->eventTypeMapper = $eventTypeMapper;
        $this->campusMapper = $campusMapper;
    }


    /**
     * View an event's detail screen
     *
     * @param Event $event
     * @throws ServiceNotFound
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
     * @throws DefinitionException
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws ServiceNotFound
     * @throws UnexpectedValueException
     */
    public function getEventModify(Event $event): void
    {

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
            $recurrence = (new EventRecurrenceMapper($this->eventMapper))->getSeriesRecurrence($event);
        }

        /* Group Folders that you can choose, plus their current selection states
         */
        $groupFolders = $this->getSelectableFolders($event);

        /* Only provide Campus options in Level dropdown if campuses have been defined
         */
        $selectedUser = $this->getSelectedUser();
        $campusList = $this->campusMapper->getAvailableCampusesForUser($selectedUser, UserAccess::ACCESS_WRITE);

        $this->getResponse()->content->set($this->buildHtmlOutput('@calendarEvent/create.twig', [
            'event' => $event,
            'action' => 'Update',
            'eventTypes' => $this->eventTypeMapper->findAll(),
            'roles' => [
                'all' => $this->roleMapper->findAll(),
                'selected' => $selectedRoles
            ],
            'recurrence' => $recurrence,
            'personalLevel' => Event::ACCESS_PERSONAL,
            'levels' => [
                'all' => new EventLevelCollection($groupFolders['folders'], $campusList),
                'selected' => $event->getAccess()
            ],
            'groupFolders' => $groupFolders,
            'campuses' => [
                'all' => $campusList,
                'selected' => $selectedCampuses,
            ],
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


    /**
     * Get the user specified by the querystring params
     * Defaults to current user if one wasn't specified
     *
     * @return User
     */
    private function getSelectedUser(): User
    {
        /* A user might have been optionally passed on the url
         * Otherwise default to the current user
         */
        $selectedUser = $this->getUser();

        if (isset($this->getRequest()->query['userId']) && is_numeric($this->getRequest()->query['userId'])) {
            try {
                $selectedUser = (new \Schoolbox\UserMapper())->findById($this->getRequest()->query['userId']);
            } catch (Exception $e) {

                /* TODO: User not found
                 */
            }
        }

        return $selectedUser;
    }

    /**
     * You have WRITE access to this list of groups (folders) from which to choose when using
     * the event's Group level option
     *
     * @param Event $event
     * @return Collection|Folder\Model[]
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws UnexpectedValueException
     */
    private function getFolders(Event $event)
    {

        /* Ones that it's already in, from previous selections
         */
        $alreadyInFolderList = $event->getFolderCollection();

        /* Get data about the Folders
         */
        $tempFolderIds = [];

        foreach ($alreadyInFolderList as $current) {
            $tempFolderIds[] = $current->getId();
        }

        /* If user has write access to, or is a group manager,
         * but isn't involved in the homepage used to get here:
         * force the homepage into the checkbox list
         */
        $homepageId = (int)$this->getRequest()->query['homepageId'];

        if ($homepageId && !in_array($homepageId, $tempFolderIds, false) && FolderAccess::getInstance()->getFolderAccess($this->getUser(), $homepageId) >= UserAccess::ACCESS_WRITE) {
            $tempFolderIds[] = $homepageId;
        }

        return $this->folderMapper->findByColumnList(['id' => $tempFolderIds]);
    }


    /**
     * Get a list of checkboxes which you can select and also include their current selection state
     *
     * For some reason this is extremely lengthy; probably try to slim it down somewhat
     *
     * @param Event $event
     * @return array
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws UnexpectedValueException
     * @throws DefinitionException
     */
    private function getSelectableFolders(Event $event): array
    {
        $selectedUser = $this->getSelectedUser();

        /* Folders to which $selectedUser has WRITE access
         */
        $currentUserFolderIds = $this->folderMapper->findFolderIdsForUser($selectedUser, true, UserAccess::ACCESS_WRITE);

        /* Possible options, based on calendar user's involvement. Will intersect with current user
         * This is to allow you to choose options when adding an event on someone else's behalf;
         *
         * Your WRITE folders intersected with their READ+ folders gives you common ground
         * (These are the places where you can alter content that your $selectedUser can read)
         * SA-4396
         */
        $calendarUserFolderList = $this->folderMapper->findForUser($selectedUser, true, UserAccess::ACCESS_READ);

        /* All the checkbox options, deduped, are here
         */
        $allFolderList = $this->folderMapper->createCollection();

        /* Ones that you could possibly tick (intersect of write level of logged in user and read level of calendar user)
         * TODO: Replace with array_intersect and $collection->indexBy('id');
         */
        foreach ($calendarUserFolderList as $current) {
            $currentId = $current->getId();

            if (in_array($currentId, $currentUserFolderIds, false) || $this->getUser()->isSuperuser()) {
                $allFolderList[$currentId] = $current;
            }
        }

        /* Ones that have been ticked in the past
         * A simple stack of ids for checkboxes which should already be ticked
         */
        $previousInvolvement = [];

        /* A list of groups to choose. If the event was associatd with a folder outside of the intersect
         * just ram it in so it's a union of:
         *
         * homepage you came from + what was previously there + (folders you can WRITE ^ folders $selectedUser can READ)
         */
        $allFolders = $this->getFolders($event);

        foreach ($allFolders as $currentFolder) {
            if (!isset($allFolderList[$currentFolder->getId()])) {
                $allFolderList[$currentFolder->getId()] = $currentFolder;
            }

            $previousInvolvement[] = $currentFolder->getId();
        }

        /* Order by title
         */
        $allFolderList->sortByCallback(static function (Folder\Model $a, Folder\Model $b) {
            return strnatcasecmp($a->getTitle(), $b->getTitle());
        });

        /* Figure out which ones should be checked
         */
        $items = [
            'folders' => $allFolderList,
            'selected' => [],
        ];

        foreach ($allFolderList as $currentFolder) {
            $alreadyInCategory = in_array($currentFolder->getId(), $previousInvolvement, false);
            $fromHomepage      = (($this->getRequest()->query['level'] == Event::ACCESS_GROUP) && ($this->getRequest()->query['homepageId'] == $currentFolder->getId()));

            if ($alreadyInCategory || $fromHomepage) {
                $items['selected'][] = $currentFolder->getId();
            }
        }

        return $items;
    }
}
