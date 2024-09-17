<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Event;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Alaress_Date_Locale_Config;
use DateTimeInterface;
use InvalidArgumentException;
use LogicException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\Event\Attendance\Payload;
use Schoolbox\Calendar\EventLevelCollection;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\EventRecurrenceMapper;
use Schoolbox\Calendar\EventRelations;
use Schoolbox\Calendar\EventType;
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
use Schoolbox\Resource\Folder\Translator\ModelToArray\LinkOnly as FolderLinkOnlyTranslator;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\User\Translator\ModelToArray as UserTranslator;
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
     * @var Attendance\Mapper
     */
    private $attendanceMapper;


    /**
     * @var FolderAccess
     */
    private FolderAccess $folderAccess;


    /**
     * @var Validator
     */
    private Validator $validator;


    /**
     * Controller constructor.
     *
     * @param Error\Mapper      $errorMapper
     * @param RoleMapper        $roleMapper
     * @param Folder\Mapper     $folderMapper
     * @param EventMapper       $eventMapper
     * @param EventTypeMapper   $eventTypeMapper
     * @param CampusMapper      $campusMapper
     * @param UserMapper        $userMapper
     * @param Attendance\Mapper $attendanceMapper
     * @param FolderAccess      $folderAccess
     * @param Validator         $validator
     */
    public function __construct(
        Error\Mapper $errorMapper,
        RoleMapper $roleMapper,
        Folder\Mapper $folderMapper,
        EventMapper $eventMapper,
        EventTypeMapper $eventTypeMapper,
        CampusMapper $campusMapper,
        UserMapper $userMapper,
        Attendance\Mapper $attendanceMapper,
        FolderAccess $folderAccess,
        Validator $validator,
    ) {
        $this->errorMapper = $errorMapper;
        $this->roleMapper = $roleMapper;
        $this->folderMapper = $folderMapper;
        $this->eventMapper = $eventMapper;
        $this->eventTypeMapper = $eventTypeMapper;
        $this->campusMapper = $campusMapper;
        $this->userMapper = $userMapper;
        $this->attendanceMapper = $attendanceMapper;
        $this->folderAccess = $folderAccess;
        $this->validator = $validator;
    }


    /**
     * View an event's detail screen
     *
     * @param Event $event
     * @throws Exception
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getEvent(Event $event): void
    {
        $helper = new Helper($this->roleMapper, $this->folderMapper);

        try {
            $attendanceStatus = $this->attendanceMapper->findOneByColumnList([
                'event_id' => $event->getId(),
                'user_id'  => $this->getUser()->getId()
            ])->getStatus();
        } catch (Exception $e) {
            $attendanceStatus = null;
        }

        $payload = (new Payload($this->di, $this->attendanceMapper))->generate($event, $this->getUser());

        $this->response = $this->responseFromTemplate('@calendarEvent/event.twig', [
            'event' => $event,
            'level' => $helper->getEventLevel($event),
            'campusCollection' => $helper->getCampusCollection($event),
            'roleCollection' => $helper->getRoleCollection($event),
            'folderCollection' => $helper->getFolderCollection($event),
            'user' => $this->getUser(),

            /* Modify perms are calculated in the attendance list fragment
             * This is synonymous with calling (new Event\Access())->checkExisting() again
             */
            'canModify' => $payload['currentUser']['canModify'],

            /* Attendance info
             * Current attendee info and your current rsvp status
             */
            'attendance' => [
                'payload' => $payload,
                'status' => [
                    'current' => $attendanceStatus,
                ],
                'level' => [
                    'current' => $event->getAttendance(),
                ],
            ],
        ], $event->getTitle());
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
     * @throws UnexpectedValueException
     * @throws \Schoolbox\Application\Exception\Exception
     */
    public function getCreate(): void
    {
        /* Set up default values
         */
        $event = (new Event())
            ->setTitle((string)$this->requestQuery->get('title'))
            ->setAuthor($this->getUser())
            ->setAccess((int)$this->requestQuery->get('level'))
            ->setAllDay((int)$this->requestQuery->get('allDay'))
            ->setPublishDateTime(Alaress_Date_Format::now());

        $event->getType()->setId((int)$this->requestQuery->get('type'));

        $event->setupDates($this->requestQuery->get('date'));

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
        $selectedCampuses = $this->getUser()->getCampusCollection()->getPropertyValues('id');

        /* Folders that you can choose, plus their current selection states
         */
        $folders = $this->getSelectableFolders($event);

        /* Only provide Campus options in Level dropdown if campuses have been defined
         */
        $selectedUser = $this->getSelectedUser();
        $campusList   = $this->getUser()->isSuperuser()
            ? $this->campusMapper->findAll()
            : $this->campusMapper->getAvailableCampusesForUser($selectedUser, UserAccess::ACCESS_WRITE);

        /* Guest List is initially on
         */
        $event->setShowAttendees(true);

        $this->response = $this->responseFromTemplate('@calendarEvent/create.twig', [
            'event' => $event,
            'user' => $selectedUser,
            'eventTypes' => $this->eventTypeMapper->findAll(),
            'recurrence' => $recurrence,
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

            /* The available attendance levels which can be chosen
             */
            'attendanceLevels' => [
                Attendance\Model::LEVEL_NO_ATTENDANCE => 'None',
                Attendance\Model::LEVEL_INVITE_ONLY => 'Invite Only',
                Attendance\Model::LEVEL_OPEN_ATTENDANCE => 'Open'
            ],

            /* Specifically hides attendance inputs when you choose this setting
             */
            'attendanceLevelNoAttendance' => Attendance\Model::LEVEL_NO_ATTENDANCE,

            /* Whether the current user can invite others; if you're a student, then it depends on the
             * Policy config var's setting
             */
            'canInvite' => (new Event\Attendance\ValidCanInvite())->isSatisfiedBy($this->getUser(), $this->getConfigLoader()->get('calendar_student_can_invite')),

            'guests' => [],
            'returnUrl' => Redirect::queryStringEncodedParam($this->requestQuery->get('returnUrl') ?? Redirect::encode('/')),
        ], 'Create event');
    }


    /**
     * Modify an event's settings
     *
     * @param Event $event
     * @throws DefinitionException
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws UnexpectedValueException
     * @throws \Schoolbox\Application\Exception\Exception
     */
    public function getModify(Event $event): void
    {
        /*
         * if the publish date is null, then set to the event start date
         */
        if (!$event->getPublishDateTime()) {
            $event->setPublishDateTime(Alaress_Date_Format::now());
        }
        /* Preselected campuses
         * Note: this collection could be one of three data types
         */
        $selectedCampuses = $event->getCampusCollection()->getPropertyValues('id');

        /* Preselected roles
         */
        $selectedRoles = $event->getRoleCollection()->getPropertyValues('roleId');

        /* Recurrence
         */
        $recurrence = $event->getSeriesId() ? (new EventRecurrenceMapper($this->eventMapper))->getSeriesRecurrence($event) : [];

        /* Folders that you can choose, plus their current selection states
         */
        $folders = $this->getSelectableFolders($event);

        /* Only provide Campus options in Level dropdown if campuses have been defined
         */
        $selectedUser = $this->getSelectedUser();
        $campusList = $this->getUser()->isSuperuser()
            ? $this->campusMapper->findAll()
            : $this->campusMapper->getAvailableCampusesForUser($selectedUser, UserAccess::ACCESS_WRITE);

        $this->response = $this->responseFromTemplate('@calendarEvent/create.twig', [
            'event' => $event,
            'user' => $selectedUser,
            'eventTypes' => $this->eventTypeMapper->findAll(),
            'recurrence' => $recurrence,
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

            /* The available attendance levels which can be chosen
             */
            'attendanceLevels' => [
                Attendance\Model::LEVEL_NO_ATTENDANCE => 'None',
                Attendance\Model::LEVEL_INVITE_ONLY => 'Invite Only',
                Attendance\Model::LEVEL_OPEN_ATTENDANCE => 'Open'
            ],

            /* Specifically hides attendance inputs when you choose this setting
             */
            'attendanceLevelNoAttendance' => Attendance\Model::LEVEL_NO_ATTENDANCE,

            /* Whether the current user can invite others; if you're a student, then it depends on the
             * Policy config var's setting
             */
            'canInvite' => (new Event\Attendance\ValidCanInvite())->isSatisfiedBy($this->getUser(), $this->getConfigLoader()->get('calendar_student_can_invite')),

            'guests' => $this->attendanceMapper->findAttendances($event),
            'returnUrl' => Redirect::queryStringEncodedParam($this->requestQuery->get('returnUrl') ?? Redirect::encode('/')),
        ], "Modify {$event->getTitle()}");
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
        $userId = (int)$this->requestQuery->get('userId');

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
        $homepageId = (int)$this->requestQuery->get('homepageId');

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
            $fromHomepage      = ((int)$this->requestQuery->get('level') === Event::ACCESS_GROUP) && ((int)$this->requestQuery->get('homepageId') === $currentFolder->getId());

            if ($alreadyInCategory || $fromHomepage) {
                $items['selected'][] = $currentFolder->getId();
            }
        }

        return $items;
    }


    /**
     * Create an event for person specified by userId
     * (remember, you can create events for other people)
     * @throws Exception
     */
    public function postCreate(): void
    {
        // This will throw a SchoolboxException with a helpful message if the request is invalid.
        $this->validator->validateForPostCreate($this->requestPost);

        $userId = (int)($this->requestPost['user']['id'] ?? $this->requestPost['userId'] ?? $this->user->getId());
        $authorId = (
            (
                array_key_exists('author', (array)$this->requestPost) &&
                array_key_exists('id', (array)$this->requestPost['author'])
            )
            ? (int)$this->requestPost['author']['id']
            : (
                // Key 'authorId' is posted by the create/modify event form.
                (array_key_exists('authorId', (array)$this->requestPost))
                ? (int)$this->requestPost['authorId']
                : $userId
            )
        );

        $event = new Event();
        $event->setOwner($this->userMapper->createModel()->setId($userId));
        $event->setAuthor($this->userMapper->createModel()->setId($authorId));

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
     * @throws \Exception
     */
    public function postModify(Event $event): void
    {
        // This will throw a SchoolboxException with a helpful message if the request is invalid,
        // and return a possibly modified request with compatible strings converted to ints or bools for example.
        // The $validRequestPost does not contain all possible fields, but all fields contained in the validRequestPost
        // require no further validation.
        $validRequestPost = $this->validator->validateForPostModify($event, $this->requestPost);

        /* Check owner still exists
         * If not - pass ownership to the author
         */
        try {
            $owner = $this->userMapper->findById($validRequestPost['user']['id'] ?? $event->getOwner()->getId());
        } catch (Exception $e) {
            $owner = $this->getUser();
        }

        $authorId = (int)($validRequestPost['author']['id'] ?? $validRequestPost['authorId'] ?? $event->getAuthor()->getId());

        /* Check if author still exists
         * If not - pass author to the author
         */
        try {
            $author = $this->userMapper->findById($authorId);
        } catch (Exception) {
            $author = $this->getUser();
        }

        /* Enforce ints for primary identifiers (yes that's a thing)
         */
        $accessLevel = (int)($validRequestPost['level'] ?? $event->getAccess());
        $eventTypeId = (int)($validRequestPost['type']['id'] ?? $validRequestPost['type'] ?? $event->getType()->getId());
        $title       = $validRequestPost['title'] ?? $event->getTitle();
        $location    = $validRequestPost['location'] ?? $event->getLocation();
        $detail      = $validRequestPost['detail'] ?? $event->getDetail();
        $allDay      = (int)($validRequestPost['allDay'] ?? $event->getAllDay());

        $folderIds = (
            array_key_exists('folders', (array)$validRequestPost)
            ? $this->getIdsFromDigitStringsOrKeyedArrays($validRequestPost['folders'])
            : $event->getFolderCollection()->map(fn ($folder) => $folder->getId())
        );
        $campusIds = (
            array_key_exists('campuses', (array)$validRequestPost)
            ? $this->getIdsFromDigitStringsOrKeyedArrays($validRequestPost['campuses'])
            : $event->getCampusCollection()->map(fn ($campus) => $campus->getId())
        );
        $roleIds = (
            array_key_exists('roles', (array)$validRequestPost)
            ? $this->getIdsFromDigitStringsOrKeyedArrays($validRequestPost['roles'])
            : $event->getRoleCollection()->map(fn ($role) => $role->getId())
        );

        /* Recurrence settings
         */
        $efficacy = $validRequestPost['efficacy'] ?? EventRecurrenceMapper::EFFICACY_SELF;
        $repeat   = $validRequestPost['repeat'] ?? '';

        /* Redirects
         */
        $returnUrl = $this->requestQuery->get('returnUrl') ?? Redirect::encode('/calendar');

        /* Check whether you have permissions to mutate $event into the vars provided
         */
        try {
            if (!(new Event\Access($this->folderAccess))->checkDestination($this->getUser(), $accessLevel, $author, $owner, $folderIds, $campusIds)) {
                throw $this->errorMapper->generateException(Error\Mapper::ERROR_PERM_EVENT_MODIFY);
            }
        } catch (InvalidArgumentException $e) {
            throw $this->errorMapper->generateException(Error\Mapper::ERROR_INFORMATION_MISSING);
        }

        /* Check the dates
         */
        $locale = new Alaress_Date_Locale_Config();

        $publishDate = null;

        $eventPublishDateTime = $event->getPublishDateTime()?->getUsingFormat(DateTimeInterface::RFC3339);
        if ('' !== ($validRequestPost['publishDate'] ?? $eventPublishDateTime)) {
            $publishDate = new Alaress_Date_Format(
                $validRequestPost['publishDate'] ?? $eventPublishDateTime,
                [
                    $locale->localFormat(),
                    // API docs state we accept RFC3339 without qualification, so we must accept both variants.
                    DateTimeInterface::RFC3339,
                    DateTimeInterface::RFC3339_EXTENDED
                ]
            );
        }

        $acceptedDateFormats = [
            // The localShortFormat does not include time, so is only appropriate for all day events.
            ($allDay === Event::ALL_DAY) ? $locale->localShortFormat() : $locale->localFormat(),
            // API docs state we accept RFC3339 without qualification, so we must accept both variants.
            DateTimeInterface::RFC3339,
            DateTimeInterface::RFC3339_EXTENDED
        ];
        $startDate = new Alaress_Date_Format(
            $validRequestPost['startDate'] ?? $event->getStartDateTime()->getUsingFormat(DateTimeInterface::RFC3339),
            $acceptedDateFormats
        );
        $endDate = new Alaress_Date_Format(
            $validRequestPost['endDate'] ?? $event->getEndDateTime()->getUsingFormat(DateTimeInterface::RFC3339),
            $acceptedDateFormats
        );

        /* Start and end dates
         */
        if ($allDay === Event::ALL_DAY) {
            $startDate = $startDate->startOfDay();
            $endDate   = $endDate->endOfDay();
        }

        /* All events recur, due to the dropdown "Repeat" always being set.
         *
         * Events recur at least 1 time (Single Event) or N times based on
         * the other dropdown options. This value is preopulated in the ui
         * and will always contain a date
         *
         * if it does not, or if it is malformed (ddmm/yyyy, dd/mmyyyy) just set the repeat date till the endDay->endOfDay
         */
        try {
            // Unlike the other dates, until date is currently always a date and
            // not a date+time.
            $untilDate = (new Alaress_Date_Format(
                $validRequestPost['repeat_until'] ?? $event->getEndDateTime(),
                [$locale->localShortFormat(), Alaress_Date_Format::DATABASE_DATE_FORMAT],
            ))->endOfDay();
        } catch (InvalidArgumentException) {
            $untilDate = (clone $endDate)->endOfDay();
        }
        /**
         * the repeat until date must be after the event end date:
         * If it is not, just set it to the event end date
         */
        if ($untilDate->lt($endDate)) {
            $untilDate = $endDate;
        }

        /* Don't allow start to be after end
         */
        if ($startDate->getUnixTimeStamp() > $endDate->getUnixTimeStamp()) {
            throw $this->errorMapper->generateException(Error\Mapper::ERROR_START_TIME_GREATER);
        }

        /* Attendance settings
         */
        $attendance = (int)($validRequestPost['attendance'] ?? $event->getAttendance());
        $maxAttendees = (
            array_key_exists('maxAttendees', (array)$validRequestPost)
            ? $validRequestPost['maxAttendees']
            : $event->getMaxAttendees()
        );
        $showAttendees = (bool)($validRequestPost['showAttendees'] ?? $event->getShowAttendees());

        // As of SA-10697 attendances are not reset on changing event time.
        // Prior to that, they were reset if the start or end time of the event changed.
        $attendanceDirty = false;

        /* Invited guests
         */
        $guestIds = [];
        if ((new Event\Attendance\ValidCanInvite())->isSatisfiedBy($this->getUser(), $this->getConfigLoader()->get('calendar_student_can_invite'))) {
            if (array_key_exists('guests', (array)$validRequestPost)) {
                $guestIds = $this->getIdsFromDigitStringsOrKeyedArrays($validRequestPost['guests']);
            } else {
                $this->eventMapper->populateGuests($event); // Hydrate guests in $event.
                $guestIds = $event->getGuests()->getAll() ?? []; // Note getGuests() returns a Collection of ints.
            }
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
            ->setRoleCollection(new Collection($roleIds))
            ->setFolderCollection(new Collection($folderIds))
            ->setCampusCollection(new Collection($campusIds))
            ->setGuests(new Collection($guestIds));

        /* Attendance dirty bit should be set to true if the attendance
         * setting now differs from what it previously was
         */
        $event
            ->setMaxAttendees($maxAttendees)
            ->setShowAttendees($showAttendees)
            ->setAttendance($attendance)
            ->setAttendanceDirty($attendanceDirty);

        /* Check minimum requirements for checkbox arrays
         *
         * For campus level events, at least one campus has been selected
         * For group level events, at least one group has been selected
         */
        if ($event->isCampusLevel() && !count($campusIds)) {
            throw $this->errorMapper->generateException(Error\Mapper::ERROR_SELECT_CAMPUS);
        } elseif ($event->isGroupLevel() && !count($folderIds)) {
            throw $this->errorMapper->generateException(Error\Mapper::ERROR_INFORMATION_MISSING);
        }

        /* Set up individual relations for each event in the series
         * Event object is provided within eventRecurrenceMapper
         */
        $eventRelations = new EventRelations($accessLevel, $folderIds, $roleIds, $guestIds, $attendanceDirty);

        /* Save this event and any recurring incidents too
         */
        (new EventRecurrenceMapper($this->eventMapper))->save($event, $efficacy, $repeat, $untilDate, $startDate, $endDate, $accessLevel, $eventRelations);

        if ($this->isJsonRequest()) {
            $this->eventMapper->populateGuests($event); // Reload guests to ensure the response matches the database representation.

            /**
             * @var eventType $eventType EventType
             */
            $eventType = $this->eventTypeMapper->findById($event->getType()->getId());
            $event->setType($eventType);

            $userTranslator = new UserTranslator();
            $this->response = $this->responseAsJson(
                [
                    'id' => $event->getId(),
                    'user' => $userTranslator->convertLinkedUser($event->getOwner()),
                    'author' => $userTranslator->convertLinkedUser($event->getAuthor()),
                    'allDay' => ($event->getAllDay() === 1), // Convert from int to boolean.
                    'efficacy' => $efficacy,
                    'title' => $event->getTitle(),
                    'startDate' => $event->getStartDateTime()->getJson(),
                    'endDate' => $event->getEndDateTime()->getJson(),
                    'repeat' => $repeat,
                    'repeat_until' => $untilDate->getJson(),
                    'type' => $event->getType(),
                    'location' => $event->getLocation(),
                    'detail' => $event->getDetail(),
                    'publishDate' => $event->getPublishDateTime()->getJson(),
                    'level' => $accessLevel,
                    'roles' => $this->translateRoleModelsToArrays(
                        $this->roleMapper->findByColumn('id', $event->getRoleCollection())
                    ),
                    'campuses' => $this->translateCampusModelsToArrays(
                        $this->campusMapper->findByColumn('id', $event->getCampusCollection())
                    ),
                    'folders' => $this->translateFolderModelsToArrays(
                        $this->folderMapper->findByColumn('id', $event->getFolderCollection())
                    ),
                    'attendance' => $event->getAttendance(),
                    'guests' => $this->translateUserModelsToArrays(
                        $this->userMapper->findByColumn('id', $event->getGuests()->getAll()),
                    ),
                    'maxAttendees' => $event->getMaxAttendees(),
                    'showAttendees' => $event->getShowAttendees(),
                ]
            );
        } else {
            /* Apply redirect - go back to the place from whence you came
             */
            $this->response = $this->redirectFound(Redirect::decode($returnUrl));
        }
    }


    /**
     * Moves this event to a new time, without changing its duration, attendance
     * or any other fields.
     *
     * @api   POST /calendar/event/{eventId}/move
     * @since 20.1
     *
     * @param Event $event
     */
    public function postMove(Event $event): void
    {
        // Do not modify anything except the event's start/end/allDay
        $start  = new Alaress_Date_Format($this->requestPost->get('start'), \DateTime::ATOM);
        $end    = new Alaress_Date_Format($this->requestPost->get('end'), \DateTime::ATOM);
        $allDay = $this->requestPost->getBool('allDay');
        if ($allDay) {
            $event->setStartDate($start->getDatabase());
            $event->setEndDate($end->getDatabase());
        } else {
            $event->setStartDateTime($start);
            $event->setEndDateTime($end);
        }
        $event->setAllDay($allDay);

        $payload = (new Payload($this->di, $this->attendanceMapper))->generate($event, $this->getUser());

        // And save it
        $eventRelations = new EventRelations(
            $event->getAccess(),
            $event->getFolderCollection()->getPropertyValues('id'),
            $event->getRoleCollection()->getPropertyValues('roleId'),
            array_column($payload['attendees']['guests'], 'id'),
            false
        );

        // Efficacy is only this item for now.
        // It should be able to be: all instances, or all in the future
        (new EventRecurrenceMapper($this->eventMapper))->save(
            $event,
            EventRecurrenceMapper::EFFICACY_SELF,
            null,
            null,
            $event->getStartDateTime(),
            $event->getEndDateTime(),
            $event->getAccess(),
            $eventRelations
        );

        // Return empty '200 OK' regardless of whether JSON was requested.
        $this->response = $this->responseEmpty()->withStatus(200);
    }


    /**
     * Delete an event, or a set of events from a series
     *
     * @param Event $event
     * @throws \Exception
     */
    public function postDelete(Event $event): void
    {
        /* Delete an entire series, if the seriesId is not null
         */
        if ($event->getSeriesId() !== null) {
            $efficacy = $this->requestPost->get('efficacy', EventRecurrenceMapper::EFFICACY_SELF);
            if ($efficacy === EventRecurrenceMapper::EFFICACY_SERIES) {
                $allEvents = $this->eventMapper->getAllInSeries($event);

                foreach ($allEvents as $currentEvent) {
                    $this->eventMapper->delete($currentEvent);
                }
            } elseif ($efficacy === EventRecurrenceMapper::EFFICACY_SELF) {
                $this->eventMapper->delete($event);
            }
        } else {
            $this->eventMapper->delete($event);
        }

        /* Redirect
         */
        $redirect = $this->requestQuery->get('returnUrl')
            ? Redirect::decode($this->requestQuery->get('returnUrl'))
            : $this->getSession()->getHistory();

        /* Calculate redirect for async javascript
         */
        if ($this->isXhrRequest()) {
            /* If you try to return to a place that no longer exists,
             * just go back to the main calendar view; this occurs
             * when you delete from the modify screen
             */
            $route = $this->routeGenerator->generate('calendarEvent.getEvent', ['eventId' => $event->getId()]);

            if ($route === $redirect) {
                $redirect = $this->routeGenerator->generate('calendar.getOwnCalendar');
            }

            $this->response = $this->responseAsJson([
                'callbackUrl' => $redirect
            ]);
        } else {
            if ($this->isJsonRequest()) {
                $this->response = $this->responseAsJson('')->withStatus(204); // HTTP status 204 means 'No Content'.
            } else {
                $this->response = $this->redirectFound($redirect);
            }
        }
    }

    /**
     * @param array $items of digit strings or (arrays containing an 'id' key (e.g. ["id" => 123])
     * @return array of integers
     */
    private function getIdsFromDigitStringsOrKeyedArrays(array $items): array
    {
        $ids = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                if (!array_key_exists('id', $item)) {
                    throw new InvalidArgumentException("No 'id' key supplied in object.");
                }

                $value = $item['id'];
            } else {
                $value = $item;
            }

            if (is_int($value)) {
                $id = $value;
            } elseif (is_string($value) && ctype_digit($value)) {
                $id = (int)$value;
            } else {
                throw new InvalidArgumentException("Invalid 'id' value.  Expected integer or digit string.");
            }

            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * @param Collection $roles (each element is a \Schoolbox\Role)
     * @return array
     */
    private function translateRoleModelsToArrays(Collection $roles): array
    {
        $translator = new UserTranslator();
        $roleArrays = [];
        foreach ($roles as $role) {
            $roleArrays[] = $translator->convertRole($role);
        }
        return $roleArrays;
    }

    /**
     * @param Collection $campuses (each element is a \Schoolbox\Campus)
     * @return array
     */
    private function translateCampusModelsToArrays(Collection $campuses): array
    {
        $translator = new UserTranslator();
        $campusArrays = [];
        foreach ($campuses as $campus) {
            $campusArrays[] = $translator->convertCampus($campus);
        }
        return $campusArrays;
    }

    /**
     * @param Collection $folders (each element is a Folder)
     * @return array
     */
    private function translateFolderModelsToArrays(Collection $folders): array
    {
        $translator = new FolderLinkOnlyTranslator(0);
        $folderArrays = [];
        foreach ($folders as $folder) {
            $folderArrays[] = $translator($folder);
        }
        return $folderArrays;
    }

    /**
     * @param Collection $users (each element is a User)
     * @return array
     */
    private function translateUserModelsToArrays(Collection $users): array
    {
        $translator = new UserTranslator();
        $userArrays = [];
        foreach ($users as $user) {
            $userArrays[] = $translator->convertLinkedUser($user);
        }
        return $userArrays;
    }
}
