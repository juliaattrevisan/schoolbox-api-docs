<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar\Event;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Aura\Di\Exception\ServiceNotFound;
use InvalidArgumentException;
use LogicException;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventLevelCollection;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\EventRecurrenceMapper;
use Schoolbox\Calendar\EventRelations;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\Error;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\Access as FolderAccess;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use UnexpectedValueException;


/**
 * Class Controller
 *
 * @package Schoolbox\Calendar\Event
 */
class Controller extends Base
{

    /**
     * @var Error\Mapper
     */
    private $errorMapper;


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
     * @var UserMapper
     */
    private $userMapper;


    /**
     * Controller constructor.
     *
     * @param Error\Mapper    $errorMapper
     * @param RoleMapper      $roleMapper
     * @param Folder\Mapper   $folderMapper
     * @param EventMapper     $eventMapper
     * @param EventTypeMapper $eventTypeMapper
     * @param CampusMapper    $campusMapper
     * @param UserMapper      $userMapper
     */
    public function __construct(
        Error\Mapper $errorMapper,
        RoleMapper $roleMapper,
        Folder\Mapper $folderMapper,
        EventMapper $eventMapper,
        EventTypeMapper $eventTypeMapper,
        CampusMapper $campusMapper,
        UserMapper $userMapper
    ) {
        $this->errorMapper = $errorMapper;
        $this->roleMapper = $roleMapper;
        $this->folderMapper = $folderMapper;
        $this->eventMapper = $eventMapper;
        $this->eventTypeMapper = $eventTypeMapper;
        $this->campusMapper = $campusMapper;
        $this->userMapper = $userMapper;
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
            'folderCollection'  => $helper->getFolderCollection($event),
            'attendance'       => [
                'status'       => $attendanceStatus,
                'nextAction'   => ($attendanceStatus) ? 'remove' : 'add',
            ],
        ])->setTitle($event->getTitle()));
    }


    /**
     * Create a new Event for $selectedUser
     *
     * You can create events on behalf of others; parents can do it to their own students
     * and staff can do it to others as well, via $selectedUser
     *
     * @throws DefinitionException
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws ServiceNotFound
     * @throws UnexpectedValueException
     * @throws \Schoolbox\Application\Exception\Exception
     */
    public function getCreate(): void
    {

        /* Set up default values
         */
        $event = (new Event())
            ->setTitle($this->request->query['title'])
            ->setAuthor($this->getUser())
            ->setAccess((int)$this->request->query['level'])
            ->setAllDay((int)$this->request->query['allDay'])
            ->setPublishDateTime(Alaress_Date_Format::now());

        $event->getType()->setId((int)$this->request->query['type'] ?: '');

        $event->setupDates($this->request->query['date']);

        if (!$event->getAllDay()) {
            $event->getEndDateTime()->addHours(1);
        }

        /* Roles
         */
        $selectedRoles = [];

        /* Recurrence
         */
        $recurrence = [];

        /* Campuses
         */
        $selectedCampuses = [];

        /* Folders that you can choose, plus their current selection states
         */
        $folders = $this->getSelectableFolders($event);

        /* Only provide Campus options in Level dropdown if campuses have been defined
         */
        $selectedUser = $this->getSelectedUser();
        $campusList = $this->campusMapper->getAvailableCampusesForUser($selectedUser, UserAccess::ACCESS_WRITE);

        $this->getResponse()->content->set($this->buildHtmlOutput('@calendarEvent/create.twig', [
            'event' => $event,
            'user' => $selectedUser,
            'eventTypes' => $this->eventTypeMapper->findAll(),
            'recurrence' => $recurrence,
            'personalLevel' => Event::ACCESS_PERSONAL,
            'efficacy' => [
                'self' => EventRecurrenceMapper::EFFICACY_SELF,
                'series' => EventRecurrenceMapper::EFFICACY_SERIES,
            ],
            'levels' => [
                'all' => new EventLevelCollection($folders['folders'], $campusList),
                'selected' => $event->getAccess()
            ],
            'roles' => [
                'all' => $this->roleMapper->findAll(),
                'selected' => $selectedRoles
            ],
            'folders' => [
                'all' => $folders['folders'],
                'selected' => $folders['selected'],
            ],
            'campuses' => [
                'all' => $campusList,
                'selected' => $selectedCampuses,
            ],
            'returnUrl' => Redirect::queryStringEncodedParam($this->request->query['returnUrl'] ?? Redirect::encode('/')),
        ])->setTitle('Create event'));
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
     * @throws \Schoolbox\Application\Exception\Exception
     */
    public function getModify(Event $event): void
    {

        /* Preselected campuses
         * Note: this collection could be one of three data types
         */
        $selectedCampuses = $event->getCampusCollection()->getPropertyValues('id');

        /* Preselected roles
         */
        $selectedRoles = $event->getRoleCollection()->getPropertyValues('roleId');

        /* Recurrence
         */
        $recurrence = [];

        if ($event->getSeriesId()) {
            $recurrence = (new EventRecurrenceMapper($this->eventMapper))->getSeriesRecurrence($event);
        }

        /* Folders that you can choose, plus their current selection states
         */
        $folders = $this->getSelectableFolders($event);

        /* Only provide Campus options in Level dropdown if campuses have been defined
         */
        $selectedUser = $this->getSelectedUser();
        $campusList = $this->campusMapper->getAvailableCampusesForUser($selectedUser, UserAccess::ACCESS_WRITE);

        $this->getResponse()->content->set($this->buildHtmlOutput('@calendarEvent/create.twig', [
            'event' => $event,
            'user' => $selectedUser,
            'eventTypes' => $this->eventTypeMapper->findAll(),
            'recurrence' => $recurrence,
            'personalLevel' => Event::ACCESS_PERSONAL,
            'efficacy' => [
                'self' => EventRecurrenceMapper::EFFICACY_SELF,
                'series' => EventRecurrenceMapper::EFFICACY_SERIES,
            ],
            'levels' => [
                'all' => new EventLevelCollection($folders['folders'], $campusList),
                'selected' => $event->getAccess()
            ],
            'roles' => [
                'all' => $this->roleMapper->findAll(),
                'selected' => $selectedRoles
            ],
            'folders' => [
                'all' => $folders['folders'],
                'selected' => $folders['selected'],
            ],
            'campuses' => [
                'all' => $campusList,
                'selected' => $selectedCampuses,
            ],
            'returnUrl' => Redirect::queryStringEncodedParam($this->request->query['returnUrl'] ?? Redirect::encode('/')),
        ])->setTitle("Modify {$event->getTitle()}"));
    }


    /**
     * Get the user specified by the querystring params
     * Defaults to current user if one wasn't specified
     *
     * @return User
     * @throws \Schoolbox\Application\Exception\Exception
     */
    private function getSelectedUser(): User
    {
        /* A userId might have been optionally passed on the url
         * Otherwise default to the current user
         */
        $selectedUser = $this->getUser();
        $userId = (int)$this->request->query['userId'];

        if ($userId) {
            try {
                $selectedUser = $this->userMapper->findById($userId);
            } catch (Exception $e) {
                throw $this->errorMapper->generateException(Error\Mapper::ERROR_USER_NOT_FOUND);
            }
        }

        return $selectedUser;
    }


    /**
     * You have WRITE access to this list of (folders) from which to choose when using
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

        /* Groups that were chosen for this even, from previous selections
         */
        $tempFolderIds = $event->getFolderCollection()->getPropertyValues('id');

        /* If user has write access to, or is a group manager,
         * but isn't involved in the homepage used to get here:
         * force the homepage into the checkbox list
         */
        $homepageId = (int)$this->request->query['homepageId'];

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
     * @throws DefinitionException
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws UnexpectedValueException
     * @throws \Schoolbox\Application\Exception\Exception
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

        /* A list of groups to choose. If the event was associated with a folder outside of the intersect
         * just ram it in so it's a union of:
         *
         * homepage you came from + what was previously there + (folders you can WRITE ^ folders $selectedUser can READ)
         *
         * This is to avoid you losing settings when you could edit the event, but not its involved folders
         * (I think)
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
            $fromHomepage      = ((int)$this->request->query['level'] === Event::ACCESS_GROUP) && ((int)$this->request->query['homepageId'] === $currentFolder->getId());

            if ($alreadyInCategory || $fromHomepage) {
                $items['selected'][] = $currentFolder->getId();
            }
        }

        return $items;
    }


    /**
     * Create an event for person specified by userId
     * (remember, you can create events for other people)
     */
    public function postCreate(): void
    {
        $event = new Event();
        $event->setOwner($this->userMapper->createModel()->setId($this->request->post['userId']));

        $this->postModify($event);
    }


    /**
     * Update $event with $_POST data
     *
     * There's some validation checks to ensure that you can write to the $_POST args specified
     *
     * @param Event $event
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws UnexpectedValueException
     * @throws \Schoolbox\Application\Exception\Exception
     */
    public function postModify(Event $event): void
    {

        /* Check owner still exists
         * If not - pass ownership to the author
         */
        try {
            $owner = $this->userMapper->findById($event->getOwner()->getId());
        } catch (Exception $e) {
            $owner = $this->getUser();
        }

        /* Check if author still exists
         * If not - pass author to the author
         */
        try {
            $author = $this->userMapper->findById($this->request->post['authorId']);
        } catch (Exception $e) {
            $author = $this->getUser();
        }

        /* Enforce ints for primary identifiers (yes that's a thing)
         */
        $folders = $this->request->post['folder'] ? array_map('intval', $this->request->post['folder']) : [];
        $campuses = $this->request->post['campus'] ? array_map('intval', $this->request->post['campus']) : [];
        $accessLevel = (int)$this->request->post['level'];
        $eventTypeId = (int)$this->request->post['type'];
        $title = $this->request->post['title'];
        $location = $this->request->post['location'];
        $detail = $this->request->post['detail'];
        $allDay = (int)$this->request->post['allDay'];
        $roles = $this->request->post['roles'] ?? [];

        /* Recurrence settings
         */
        $efficacy = $this->request->post['efficacy'];
        $repeat = $this->request->post['repeat'];

        /* Redirects
         */
        $returnUrl = '' !== (string)$this->request->query['returnUrl'] ? $this->request->query['returnUrl'] : Redirect::encode('/calendar');

        /* Check whether you have permissions to mutate $event into the vars provided
         */
        $folderAccess = FolderAccess::getInstance();

        if (!(new Event\Access($folderAccess))->checkDestination($this->getUser(), $accessLevel, $author, $owner, $folders, $campuses)) {
            throw $this->errorMapper->generateException(Error\Mapper::ERROR_PERM_EVENT_MODIFY);
        }

        /* Check the dates
         */
        $publishDate = null;

        if ('' !== $this->request->post['publishDate']) {
            $publishDate = Alaress_Date_Format::newLocal($this->request->post['publishDate']);
        }

        /* Events recur until this time
         */
        $untilDate = null;

        if ('' !== $this->request->post['repeat_until']) {
            $untilDate = Alaress_Date_Format::newLocalShort($this->request->post['repeat_until'])->endOfDay();
        }

        /* Start and end dates
         */
        if ($allDay === Event::ALL_DAY) {
            $startDate = Alaress_Date_Format::newLocalShort($this->request->post['startDate'])->startOfDay();
            $endDate = Alaress_Date_Format::newLocalShort($this->request->post['endDate'])->endOfDay();
        } else {
            $startDate = Alaress_Date_Format::newLocal($this->request->post['startDate']);
            $endDate = Alaress_Date_Format::newLocal($this->request->post['endDate']);
        }

        /* Don't allow start to be after end
         */
        if ($startDate->getUnixTimeStamp() > $endDate->getUnixTimeStamp()) {
            throw $this->errorMapper->generateException(Error\Mapper::ERROR_START_TIME_GREATER);
        }

        /* Attendance settings
         */
        $attendance = (int)$this->request->post['attendance'];
        $attendanceChanged = (int)$event->getAttendance() !== $attendance;

        /* Decide if the Attendance settings need to be reset
         *
         * You don't need to change RSVP statuses if something non-critical changes such as
         * updating the description to fix spelling mistakes, changing headings, etc.
         *
         * For now, changing datetimes requires people to respond again
         */
        $attendanceDirty = (int)(
            ($event->getStartDateTime()->getUnixTimeStamp() !== $startDate->getUnixTimeStamp()) ||
            ($event->getEndDateTime()->getUnixTimeStamp() !== $endDate->getUnixTimeStamp()));

        /* Invited guests
         */
        $guests = [];

        if ('' !== (string)$this->request->post['guests']) {
            $guests = explode(',', $this->request->post['guests']);
        }

        /* Assign values to $event instance
         */
        $event
            ->setTitle($title)
            ->setOwner($owner)
            ->setAuthor($author)
            ->setAccess($accessLevel)
            ->setType($this->eventTypeMapper->createModel()->setId($eventTypeId))
            ->setLocation($location)
            ->setDetail($detail)
            ->setStartDateTime($startDate)
            ->setEndDateTime($endDate)
            ->setPublishDateTime($publishDate)
            ->setAllDay($allDay)
            ->setRoleCollection(new Collection($roles))
            ->setFolderCollection(new Collection($folders))
            ->setCampusCollection(new Collection($campuses))
            ->setGuests(new Collection($guests));

        /* Attendance dirty bit should be set to true if the attendance
         * setting now differs from what it previously was
         */
        $event
            ->setAttendance($attendanceChanged)
            ->setAttendanceDirty($attendanceDirty);

        /* Check minimum requirements for checkbox arrays
         *
         * For campus level events, at least one campus has been selected
         * For group level events, at least one group has been selected
         */
        if ($event->isCampusLevel() && !count($campuses)) {
            throw $this->errorMapper->generateException(Error\Mapper::ERROR_SELECT_CAMPUS);
        } elseif ($event->isGroupLevel() && !count($folders)) {
            throw $this->errorMapper->generateException(Error\Mapper::ERROR_INFORMATION_MISSING);
        }

        /* Set up individual relations for each event in the series
         * Event object is provided within eventRecurrenceMapper
         */
        $eventRelations = new EventRelations($accessLevel, $folders, $roles, $guests, $attendanceDirty);

        /* Save this event and any recurring incidents too
         */
        (new EventRecurrenceMapper($this->eventMapper))->save($event, $efficacy, $repeat, $untilDate, $startDate, $endDate, $accessLevel, $eventRelations);

        /* Apply redirect - go back to the place from whence you came
         */
        $this->response->redirect->to(Redirect::decode($returnUrl));
    }


    /**
     * Delete an event, or a set of events from a series
     *
     * @param Event $event
     * @throws Exception
     */
    public function postDelete(Event $event): void
    {

        /* Delete an entire series, if the seriesId is not null
         */
        if ($event->getSeriesId() !== null) {
            if ($this->request->post['efficacy'] === EventRecurrenceMapper::EFFICACY_SERIES) {
                $allEvents = $this->eventMapper->getAllInSeries($event);

                foreach ($allEvents as $currentEvent) {
                    $this->eventMapper->delete($currentEvent);
                }
            } elseif ($this->request->post['efficacy'] === EventRecurrenceMapper::EFFICACY_SELF) {
                $this->eventMapper->delete($event);
            }
        } else {
            $this->eventMapper->delete($event);
        }

        /* Redirect
         */
        $redirect = Redirect::decode($this->request->query['returnUrl']);

        /* Calculate redirect for async javascript
         */
        if ($this->request->isXhr()) {
            $this->getResponse()->content->set($this->buildJsonOutput([
                'callbackUrl' => $redirect
            ]));
        } else {
            $this->response->redirect->to($redirect);
        }
    }
}
