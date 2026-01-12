<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Event;

use Alaress\Authorizer\Failure\Forbidden;
use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Alaress_Date_Locale_Config;
use Crell\ApiProblem\ApiProblem;
use DateTimeInterface;
use External_Timetable;
use InvalidArgumentException;
use LogicException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Application\Exception\NotFound;
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
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error;
use Schoolbox\Error\Mapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access as FolderAccess;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\ResourceBooking\Item\Mapper as BookingItemMapper;
use Schoolbox\ResourceBooking\Item\Model as BookingItemModel;
use Schoolbox\ResourceBooking\Item\ModerationType\Model as BookingModerationTypeModel;
use Schoolbox\ResourceBooking\Booking\Model as BookingModel;
use Schoolbox\ResourceBooking\Booking\Status\Mapper as BookingStatusMapper;
use Schoolbox\ResourceBooking\Booking\Status\Model as BookingStatusModel;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Translator\ModelToArray\LinkOnly as FolderLinkOnlyTranslator;
use Schoolbox\ResourceBooking\Item\ModerationType\Model as ResourceBookingModerationTypeModel;
use Schoolbox\ResourceBooking\Booking\Mapper as ResourceBookingMapper;
use Schoolbox\ResourceBooking\Booking\Model as ResourceBookingModel;
use Schoolbox\ResourceBooking\Booking\Status\Mapper as ResourceBookingStatusMapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\External\Event\Mapper as ExternalEventMapper;
use Schoolbox\ResourceBooking\Booking\Validation;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\User\Translator\ModelToArray as UserTranslator;
use Schoolbox\UserMapper;
use UnexpectedValueException;
use Schoolbox\Editor\Templates\Helper as EditorTemplateHelper;

/**
 * Class Controller
 *
 * @package Schoolbox\Calendar\Event
 */
class Controller extends Base
{
    private const int HTTP_STATUS_CODE_OK = 200;
    private const int HTTP_STATUS_CODE_NO_CONTENT = 204;
    private const int HTTP_STATUS_CODE_FORBIDDEN = 403;
    private const int HTTP_STATUS_CODE_NOT_FOUND = 404;
    private const int HTTP_STATUS_CODE_SERVER_ERROR = 500;


    /**
     * Controller constructor.
     *
     * @param Mapper                      $errorMapper
     * @param RoleMapper                  $roleMapper
     * @param Folder\Mapper               $folderMapper
     * @param EventMapper                 $eventMapper
     * @param EventTypeMapper             $eventTypeMapper
     * @param CampusMapper                $campusMapper
     * @param UserMapper                  $userMapper
     * @param Attendance\Mapper           $attendanceMapper
     * @param FolderAccess                $folderAccess
     * @param Validator                   $validator
     * @param EditorTemplateHelper        $editorTemplateHelper
     * @param BookingItemMapper           $bookingItemMapper
     * @param ResourceBookingStatusMapper $bookingStatusMapper
     * @param ResourceBookingMapper       $bookingMapper
     * @param TermMapper                  $termMapper
     * @param ExternalEventMapper         $externalEventMapper
     * @param External_Timetable          $externalTimetable
     */
    public function __construct(
        private Error\Mapper $errorMapper,
        private RoleMapper $roleMapper,
        private Folder\Mapper $folderMapper,
        private EventMapper $eventMapper,
        private EventTypeMapper $eventTypeMapper,
        private CampusMapper $campusMapper,
        private UserMapper $userMapper,
        private Attendance\Mapper $attendanceMapper,
        private FolderAccess $folderAccess,
        private Validator $validator,
        private EditorTemplateHelper $editorTemplateHelper, //Helper for editor templates
        private BookingItemMapper $bookingItemMapper,
        private BookingStatusMapper $bookingStatusMapper,
        private ResourceBookingMapper $bookingMapper,
        private TermMapper $termMapper,
        private ExternalEventMapper $externalEventMapper,
        private External_Timetable $externalTimetable,
    ) {
    }


    /**
     * View an event's detail screen
     *
     * @param Event $event
     * @throws MapperException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface|\Twig\Error\Error
     */
    public function getEvent(Event $event): void
    {
        $helper = new Helper($this->roleMapper, $this->folderMapper, $this->bookingMapper);

        try {
            $attendanceStatus = $this->attendanceMapper->findOneByColumnList([
                'event_id' => $event->getId(),
                'user_id'  => $this->getUser()->getId()
            ])->getStatus();
        } catch (MapperException) {
            $attendanceStatus = null;
        }

        $payload = new Payload($this->di, $this->attendanceMapper)->generate($event, $this->getUser());

        $data = [
            'event' => $event,
            'level' => $helper->getEventLevel($event),
            'campusCollection' => $helper->getCampusCollection($event),
            'roleCollection' => $helper->getRoleCollection($event),
            'folderCollection' => $helper->getFolderCollection($event),
            'resourceBookings' => $this->bookingMapper->findByColumn('event_id', $event->getId()),
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
        ];

        // Only include resource booking data if the resource booking module is enabled.
        if ($this->getConfigLoader()->get(ConfigMapper::NAME_RESOURCE_BOOKING_ENABLED)) {
            $data['resourceBookingCollection'] = $helper->getResourceBookingCollection($event);
        }

        $this->response = $this->responseFromTemplate('@calendarEvent/event.twig', $data, $event->getTitle());
    }


    /**
     * Create a new Event for $selectedUser
     *
     * You can create events on behalf of others; parents can do it to their own students
     * and staff can do it to others as well, via $selectedUser
     *
     * @throws DefinitionException
     * @throws MapperException
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
            'resources' => [],

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
            'editorTemplates' => $this->editorTemplateHelper->getTemplatesForCategory(),
        ], 'Create event');
    }


    /**
     * Modify an event's settings
     *
     * @param Event $event
     * @throws DefinitionException
     * @throws MapperException
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

        $existingBookings = $this->bookingMapper->findByColumn('event_id', $event->getId());
        $resourceIds = $existingBookings->map(fn ($booking) => $booking->getResource()->getId());

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
            'resources' => $this->bookingItemMapper->findByColumn('id', $resourceIds),

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
            'editorTemplates' => $this->editorTemplateHelper->getTemplatesForCategory(),
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
            } catch (MapperException $e) {
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
     * @throws MapperException
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
     * @throws MapperException
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
     * @throws MapperException
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
     * @throws MapperException
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

        // cache old start and end for resource booking move
        $existingEvent = false;
        $oldStart = $oldEnd = null;
        if ($event->getId()) {
            $oldStart = clone $event->getStartDateTime();
            $oldEnd = clone $event->getEndDateTime();
            $existingEvent = true;
        }

        /* Check owner still exists
         * If not - pass ownership to the author
         * Event owner is deliberately not modifiable by API requests.
         */
        try {
            $owner = $this->userMapper->findById($event->getOwner()->getId());
        } catch (MapperException $e) {
            $owner = $this->getUser();
        }

        $authorId = (int)($validRequestPost['author']['id'] ?? $validRequestPost['authorId'] ?? $event->getAuthor()->getId());

        /* Check if author still exists
         * If not - pass author to the author
         */
        try {
            $author = $this->userMapper->findById($authorId);
        } catch (MapperException) {
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
            ? array_map(fn ($folder) => $folder['id'], $validRequestPost['folders'])
            : $event->getFolderCollection()->map(fn ($folder) => $folder->getId())->getAll()
        );
        $campusIds = (
            array_key_exists('campuses', (array)$validRequestPost)
            ? array_map(fn ($campus) => $campus['id'], $validRequestPost['campuses'])
            : $event->getCampusCollection()->map(fn ($campus) => $campus->getId())->getAll()
        );
        $roleIds = (
            array_key_exists('roles', (array)$validRequestPost)
            ? array_map(fn ($role) => $role['id'], $validRequestPost['roles'])
            : $event->getRoleCollection()->map(fn ($role) => $role->getRoleId())->getAll() // Note $role is Calendar\Relation\Role, so getRoleId().
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
            // need to match the formats in \Schoolbox\Calendar\Event\Validator::getValidFormatsForDateField, or this will do nothing...
            $untilDate = (new Alaress_Date_Format(
                $validRequestPost['repeat_until'] ?? $endDate->getDatabaseDate(),
                [$locale->localShortFormat(), Alaress_Date_Format::DATABASE_DATE_FORMAT, Alaress_Date_Format::FORMAT_DATE_ALT],
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
                $guestIds = array_map(fn ($guest) => $guest['id'], $validRequestPost['guests']);
            } else {
                $this->eventMapper->populateGuests($event); // Hydrate guests in $event.
                $guestIds = $event->getGuests()->getAll(); // Note getGuests() returns a Collection of ints.
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

        // Save changes to the linked resource bookings in a separate step, after the event has been saved.
        // TODO: Consider adding a database transaction encompassing all database
        //       operations to ensure the save succeeds entirely or not at all.

        // Find resources to add/remove by comparing existing resources to resources from form submission.
        $existingBookings = $this->bookingMapper->findByColumn('event_id', $event->getId());
        $resourceIdsExisting = $existingBookings->map(fn ($booking) => $booking->getResource()->getId())->getAll();
        $resourceIdsFromForm = (
            array_key_exists('resources', (array)$validRequestPost)
            ? array_map(fn ($resource) => $resource['id'], $validRequestPost['resources'])
            : []
        );
        $resourceIdsToRemove = array_diff($resourceIdsExisting, $resourceIdsFromForm);
        $resourceIdsToAdd = array_diff($resourceIdsFromForm, $resourceIdsExisting);

        // Initialise booking error arrays.
        $bookingErrorsDelete = [];
        $bookingErrorsMove = [];
        $bookingErrorsAdd = [];

        // Delete bookings if necessary.
        if (count($resourceIdsToRemove) > 0) {
            $bookingIdsToRemove = [];
            foreach ($existingBookings as $booking) {
                if (in_array($booking->getResource()->getId(), $resourceIdsToRemove, false)) {
                    $bookingIdsToRemove[] = $booking->getId();
                }
            }
            try {
                $this->deleteBookingsSlave($event, $bookingIdsToRemove, $efficacy);
            } catch (NotFound | Forbidden $e) {
                $bookingErrorsDelete[] = $e->getMessage();
            }
        }

        // Move bookings if necessary.
        if ($existingEvent) {
            // Move any resource bookings attached to this event.
            $bookingErrorsMove = $this->moveBookings($event, $oldStart, $oldEnd, $efficacy);
        }

        // Add bookings if necessary.
        if (count($resourceIdsToAdd) > 0) {
            try {
                $this->addBookingsSlave($event, $resourceIdsToAdd, $efficacy);
            } catch (NotFound | Forbidden $e) {
                $bookingErrorsAdd[] = $e->getMessage();
            }
        }

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
                    'startDate' => $event->getStartDateTime()->toRfc3339String(),
                    'endDate' => $event->getEndDateTime()->toRfc3339String(),
                    'repeat' => $repeat,
                    'repeat_until' => $untilDate->toRfc3339String(),
                    'type' => $event->getType(),
                    'location' => $event->getLocation(),
                    'detail' => $event->getDetail(),
                    'publishDate' => $event->getPublishDateTime()->toRfc3339String(),
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
                    'bookingErrors' => array_merge($bookingErrorsDelete, $bookingErrorsMove, $bookingErrorsAdd),
                    // TODO: Add 'bookings' to this response.
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
        $oldStart = clone $event->getStartDateTime();
        $oldEnd = clone $event->getEndDateTime();
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

        // Move any resource bookings attached to this event
        $bookingErrors = $this->moveBookings($event, $oldStart, $oldEnd, EventRecurrenceMapper::EFFICACY_SELF);

        // If there were booking errors, return them
        if (count($bookingErrors)) {
            $this->response = $this->responseAsJson(
                ['errors' => $bookingErrors],
            )->withStatus(self::HTTP_STATUS_CODE_OK);
            return;
        }

        // Return empty '200 OK' regardless of whether JSON was requested.
        $this->response = $this->responseEmpty()->withStatus(self::HTTP_STATUS_CODE_OK);
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
                $allEvents = $this->eventMapper->getAllInSeries($event->getSeriesId());

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
                $this->response = $this->responseEmpty()->withStatus(self::HTTP_STATUS_CODE_NO_CONTENT);
            } else {
                $this->response = $this->redirectFound($redirect);
            }
        }
    }


    /**
     * This function is called when the form on the 'Add Resource Bookings' modal is submitted.
     * The 'Add Resource Bookings' modal appears on the 'Event Details' page when the 'Add booking(s)' button is clicked.
     *
     * @throws MapperException
     */
    public function addBookings(Event $event): void
    {
        $efficacy = $this->requestPost->get('efficacy');
        $resourceBookingIdsCommaSeparated = trim($this->requestPost->get('resourceIds', ''));
        $resourceIdStrings = (
            $resourceBookingIdsCommaSeparated === '' ? [] : explode(',', $resourceBookingIdsCommaSeparated)
        );
        $resourceIds = array_map(static fn ($resourceId) => (int)$resourceId, $resourceIdStrings);

        try {
            $results = $this->addBookingsSlave($event, $resourceIds, $efficacy);
        } catch (NotFound $e) {
            $this->response = $this->responseAsJson(
                new ApiProblem($e->getMessage())->setStatus(self::HTTP_STATUS_CODE_NOT_FOUND)->asArray()
            )->withStatus(self::HTTP_STATUS_CODE_NOT_FOUND);
            return;
        } catch (Forbidden $e) {
            $this->response = $this->responseAsJson(
                new ApiProblem($e->getMessage())->setStatus(self::HTTP_STATUS_CODE_FORBIDDEN)->asArray()
            )->withStatus(self::HTTP_STATUS_CODE_FORBIDDEN);
            return;
        }

        // Only unexpected errors require non-success code.
        // It is expected that some or all attempts to book resources may fail for validation reasons.
        $this->response = $this->responseAsJson(
            // This data structure must match type 'AddBookingsToEventResponse' defined in add-resource-bookings-modal.vue.
            [
                'nBookingsAttempted' => $results['nBookingsAttempted'],
                'nBookingsCreatedWithClash' => $results['nBookingsCreatedWithClash'],
                'nBookingsCreatedWithoutClash' => $results['nBookingsCreatedWithoutClash'],
            ]
        )->withStatus($results['httpStatusCode']);
    }


    /**
     * @param Event  $event
     * @param array  $resourceIds
     * @param string $efficacy
     * @return array
     * @throws MapperException
     * @throws Forbidden
     * @throws NotFound
     * @throws SchoolboxException
     */
    private function addBookingsSlave(Event $event, array $resourceIds, string $efficacy): array
    {
        /** @var GenericCollection<BookingItemModel> $resourceById */
        $resourceById = $this->bookingItemMapper->findByColumn('id', $resourceIds)->indexBy('id');

        if (count($resourceById) === 0) {
            throw new NotFound('Resource' . (count($resourceIds) > 1 ? 's' : '') . ' not found.');
        }

        // Check whether the user has permission to book the selected resources.
        // Note the 'calendarEvent.addBookings' permission will have been checked in Calendar/Event/Module.php.
        // A user can create a booking in the resource booking section if they can access the 'resourceBooking.createBooking' route.
        foreach ($resourceById as $resourceId => $resource) {
            if (
                !$this->authorizer->isPathAuthorizedForUser(
                    'resourceBooking.createBooking',
                    [
                        'id' => $resourceId,
                        'item' => $resource,
                    ],
                    $this->user
                )
            ) {
                throw new Forbidden(
                    "You do not have permission to create bookings for resource '{$resource->getName()}'."
                );
            }
        }

        // If the user requested the booking be added to all events in the series (and the event is part of a series)...
        if ($efficacy === EventRecurrenceMapper::EFFICACY_SERIES && $event->getSeriesId() !== null) {
            $eventById = $this->eventMapper->getAllInSeries($event->getSeriesId(), futureEventsOnly: true)
                ->indexBy('id');
        } else {
            // The event is not in a series, but treat it as a series containing one event so we don't have to deal
            // with single events separately below.
            $eventById = new GenericCollection(Event::class, [$event->getId() => $event]);
        }

        // Check whether each hypothetical booking would result in a clash.
        $isClashByResourceIdByEventId = $this->checkForClashesIfResourceBookingsAddedToEvents($resourceById, $eventById);

        // This will hold the booking models to be persisted.
        $bookingList = new Collection();

        // Counts for results data structure.
        $nBookingsAttempted = 0;
        $nBookingsCreatedWithoutClash = 0;
        $nBookingsCreatedWithClash = 0;

        // Note we are not only interested in resources booked in the future here.
        // We cannot allow multiple bookings of the same resource for a single event, even if only one is in the past.
        $bookedResourceIdsByEventId = $this->bookingMapper->getBookedResourceIdsByEventId($eventById->keys());

        // Create a list of booking models to be persisted depending on:
        //  * whether the resource has already been booked for the event
        //  * whether the booking would clash with another approved booking of the same resource.
        /** @var Event $loopEvent */
        foreach ($eventById as $eventId => $loopEvent) {
            $bookedResourceIds = $bookedResourceIdsByEventId[$eventId] ?? [];
            $isClashByResourceId = $isClashByResourceIdByEventId[$eventId] ?? [];
            /** @var BookingItemModel $resource */
            foreach ($resourceById as $resourceId => $resource) {
                if (in_array($resourceId, $bookedResourceIds, false)) {
                    continue; // This resource is already booked for this event, so skip.
                }

                $isClash = $isClashByResourceId[$resourceId] ?? false;
                if (!$isClash || $resource->getAllowClashBookings()) {
                    // Build the booking.
                    // Do not set start/end times because these are intended to be the same as in the linked event row.
                    $booking = new BookingModel()
                        ->setEvent($loopEvent)
                        ->setResource($resource)
                        ->setBookingStartAt(null) // Set as null to default to the event's start time.
                        ->setBookingEndAt(null) // Set as null to default to the event's end time.
                        ->setName($resource->getName())
                        ->setNotes('');
                    $booking->setStatus(
                        $this->bookingStatusMapper->createModel(
                            $isClash ? BookingStatusModel::STATUS_PENDING : $this->getStatusIdForNonClashBooking($booking)
                        )
                    );
                    $bookingList->add($booking);

                    if ($isClash) {
                        ++$nBookingsCreatedWithClash;
                    } else {
                        ++$nBookingsCreatedWithoutClash;
                    }
                }

                ++$nBookingsAttempted;
            }
        }

        $httpStatusCode = self::HTTP_STATUS_CODE_OK;

        try {
            // Since all saves are attempted in one call below, either all will succeed or all will fail.
            $this->bookingMapper->save($bookingList);
        } catch (MapperException) {
            $httpStatusCode = self::HTTP_STATUS_CODE_SERVER_ERROR;
            $nBookingsCreatedWithClash = 0;
            $nBookingsCreatedWithoutClash = 0;
        }

        return [
            'nBookingsAttempted' => $nBookingsAttempted,
            'nBookingsCreatedWithClash' => $nBookingsCreatedWithClash,
            'nBookingsCreatedWithoutClash' => $nBookingsCreatedWithoutClash,
            'httpStatusCode' => $httpStatusCode,
        ];
    }


    /**
     * Process a 'save' request from the 'edit booking' form that appears on the 'Event Details' page.
     * (Find the 'Event Details' page by clicking on a calendar event, then clicking 'Details' in the event modal.)
     * (Find the 'edit booking' form by clicking on an 'edit' button on a resource booking.)
     * @param Event $event
     * @param int $bookingId
     */
    public function editBooking(Event $event, int $bookingId): void
    {
        $data = $this->request->getParsedBody();
        $startDatetimeStr = $data['startDatetime'] ?? null;
        $endDatetimeStr = $data['endDatetime'] ?? null;
        $notes = $data['notes'] ?? null;
        $efficacy = $data['efficacy'] ?? null;
        $responseData = [
            // The data structure here must match type 'EditBookingResponse' in resource-booking-row.vue.
            'success' => false,
            'nRowsAffected' => 0,
            // Note 'errorMessage' is an optional field.
        ];

        if (
            $startDatetimeStr === null ||
            $endDatetimeStr === null ||
            $notes === null ||
            $efficacy === null
        ) {
            throw new SchoolboxException('Missing required data in form submission.');
        }

        try {
            $booking = $this->bookingMapper->findById($bookingId);
        } catch (MapperException) {
            // This will occur if the booking has been deleted since the form page was generated.
            $this->response = $this->responseAsJson(
                new ApiProblem('Booking not found.')->setStatus(self::HTTP_STATUS_CODE_NOT_FOUND)->asArray()
            )->withStatus(self::HTTP_STATUS_CODE_NOT_FOUND);
            return;
        }

        // Check that $bookingId is linked to $event.
        if ($booking->getEvent()->getId() !== $event->getId()) {
            throw new SchoolboxException('Booking/event mismatch when saving booking.');
        }

        try {
            $resource = $this->bookingItemMapper->findById($booking->getResource()->getId());
        } catch (MapperException $e) {
            throw new SchoolboxException('Cannot find the booked resource.');
        }

        // Check whether the user has permission to book the resource.
        // Note the 'calendarEvent.editBooking' permission will have been checked in Calendar/Event/Module.php.
        // A user can edit a booking in the resource booking section if they can access the 'resourceBooking.edit' route.
        if (
            !$this->authorizer->isPathAuthorizedForUser(
                'resourceBooking.edit',
                [
                    'id' => $resource->getId(),
                    'item' => $resource,
                ],
                $this->user
            )
        ) {
            // This will occur if permissions have changed since the form page was generated.
            $this->response = $this->responseAsJson(
                new ApiProblem("You do not have permission to edit bookings for resource '{$resource->getName()}'.")
                    ->setStatus(self::HTTP_STATUS_CODE_FORBIDDEN)
                    ->asArray()
            )->withStatus(self::HTTP_STATUS_CODE_FORBIDDEN);
            return;
        }

        $isEditOfSeries = ($efficacy === EventRecurrenceMapper::EFFICACY_SERIES && $event->getSeriesId() !== null);

        // If the user requested the booking be deleted from all events in the series (and the event is part of a series)...
        if ($isEditOfSeries) {
            $eventById = $this->eventMapper->getAllInSeries($event->getSeriesId(), futureEventsOnly: true)->indexBy('id');
        } else {
            // The event is not in a series, but treat it as a series containing one event so we don't have to deal
            // with single events separately below.
            $eventById = new GenericCollection(Event::class, [$event->getId() => $event]);
        }

        // Find all bookings to update in one query.
        $bookingIdsToUpdate = $this->bookingMapper->findIdsByResourceIdForEvents(
            $booking->getResource()->getId(),
            $eventById->keys(),
            futureBookingsOnly: $isEditOfSeries // Only allow update of past bookings if updating a single booking.
        );

        if (count($bookingIdsToUpdate) === 0) {
            // This will occur if the user attempts to edit a series consisting of past bookings.
            $this->response = $this->responseAsJson(
                new ApiProblem('No editable bookings found.  Note past bookings are not editable as part of a series.')
                    ->setStatus(self::HTTP_STATUS_CODE_NOT_FOUND)
                    ->asArray()
            )->withStatus(self::HTTP_STATUS_CODE_NOT_FOUND);
            return;
        }

        // When updating booking start/end datetimes, we don't want to set all bookings in the series to the same period.
        // Instead, we want to set each booking period to start at the same time relative to the linked event, and end
        // at the same time relative to the linked event.
        // Example: If we have a series of events recurring Monday to Friday from 1pm to 2pm, and the user edits the
        // Monday booking to start half an hour before the event, and end half an hour after the event, then we want
        // each booking in the series to also start half an hour before the event and end half an hour after the event.
        $eventStart = $booking->getEvent()->getStartDateTime();
        $eventEnd = $booking->getEvent()->getEndDateTime();
        try {
            $newBookingStart = new Alaress_Date_Format($startDatetimeStr, Alaress_Date_Format::FORMAT_RFC3339_W3C);
            $newBookingEnd = new Alaress_Date_Format($endDatetimeStr, Alaress_Date_Format::FORMAT_RFC3339_W3C);
        } catch (InvalidArgumentException) {
            throw new SchoolboxException("Start and/or end date times in invalid format.");
        }
        $startIncrementFromEventInMinutes = $eventStart?->diffInMinutes($newBookingStart, false);
        $endIncrementFromEventInMinutes = $eventEnd?->diffInMinutes($newBookingEnd, false);

        // Get the bookings to update from the ids obtained earlier, and hydrate the linked events.
        $bookingsToUpdate = $this->bookingMapper->findByColumn('id', $bookingIdsToUpdate);
        foreach ($bookingsToUpdate as $bookingToUpdate) {
            $bookingToUpdate->setEvent($eventById[$bookingToUpdate->getEvent()->getId()]);
        }

        foreach ($bookingsToUpdate as $loopBooking) {
            $loopEventStart = $loopBooking->getEvent()->getStartDateTime();
            $loopEventEnd = $loopBooking->getEvent()->getEndDateTime();

            // Only update the booking period if we have the necessary ingredients for the calculation.
            if (
                $loopEventStart !== null &&
                $loopEventEnd !== null &&
                $startIncrementFromEventInMinutes !== null &&
                $endIncrementFromEventInMinutes !== null
            ) {
                $loopBooking->setBookingStartAt($loopEventStart->addMinutes($startIncrementFromEventInMinutes));
                $loopBooking->setBookingEndAt($loopEventEnd->addMinutes($endIncrementFromEventInMinutes));
            }
            $loopBooking->setNotes($notes);
        }

        // Check whether each hypothetical moved booking would result in a clash.
        $isClashByBookingId = $this->checkForClashesIfResourceBookingsMoved(
            $bookingsToUpdate,
            new GenericCollection(
                BookingItemModel::class,
                [$booking->getResource()->getId() => $booking->getResource()]
            )
        );

        // Set booking status to pending if the booking has a clash.  Otherwise the status can stay as it was.
        foreach ($bookingsToUpdate as $bookingToUpdate) {
            if ($isClashByBookingId[$bookingToUpdate->getId()]) {
                $bookingToUpdate->setStatus(BookingStatusModel::STATUS_PENDING);
            }
        }

        try {
            $this->bookingMapper->save($bookingsToUpdate);
        } catch (MapperException) {
            throw new SchoolboxException(
                'Could not update booking' . ($isEditOfSeries ? 's in series of events' : '') . '.'
            );
        }

        $responseData['success'] = true;
        $responseData['nRowsAffected'] = count($bookingsToUpdate);
        $this->response = $this->responseAsJson($responseData)->withStatus(self::HTTP_STATUS_CODE_OK);
    }


    /**
     * Process a 'delete booking' request from the 'Event Details' page.
     * If the event is part of a series, delete all bookings of the resource from all events in the series.
     * (Find the 'Event Details' page by clicking on a calendar event, then clicking 'Details' in the event modal.)
     *
     * @param Event $event
     * @param int $bookingId
     */
    public function deleteBooking(Event $event, int $bookingId): void
    {
        $data = $this->request->getParsedBody();
        $efficacy = $data['efficacy'] ?? null;
        $responseData = [
            // The data structure here must match type 'DeleteBookingResponse' in resource-booking-row.vue.
            'success' => false,
            'nRowsAffected' => 0,
            // Note 'errorMessage' is an optional field.
        ];

        if ($efficacy === null) {
            throw new SchoolboxException('Missing required data in form submission.');
        }

        try {
            $nBookingsDeleted = $this->deleteBookingsSlave($event, [$bookingId], $efficacy);
        } catch (NotFound $e) {
            $this->response = $this->responseAsJson(
                new ApiProblem($e->getMessage())->setStatus(self::HTTP_STATUS_CODE_NOT_FOUND)->asArray()
            )->withStatus(self::HTTP_STATUS_CODE_NOT_FOUND);
            return;
        } catch (Forbidden $e) {
            $this->response = $this->responseAsJson(
                new ApiProblem($e->getMessage())->setStatus(self::HTTP_STATUS_CODE_FORBIDDEN)->asArray()
            )->withStatus(self::HTTP_STATUS_CODE_FORBIDDEN);
            return;
        }

        $responseData['success'] = true;
        $responseData['nRowsAffected'] = $nBookingsDeleted;
        $this->response = $this->responseAsJson($responseData)->withStatus(self::HTTP_STATUS_CODE_OK);
    }


    /**
     * @param Event $event
     * @param array<int> $bookingIds
     * @param string $efficacy
     * @return int - The number of bookings deleted.
     * @throws Forbidden
     * @throws NotFound
     * @throws SchoolboxException
     */
    private function deleteBookingsSlave(Event $event, array $bookingIds, string $efficacy): int
    {
        $bookings = $this->bookingMapper->findByColumnList(['id' => $bookingIds, 'event_id' => $event->getId()]);

        if (count($bookings) === 0) {
            // This will occur if the bookings have been deleted since the form page was generated.
            throw new NotFound('Booking' . (count($bookingIds) > 1 ? 's' : '') . ' not found.');
        }

        $resourceIds = []; // Remember the set of resourceIds to be booked.  It will be used below.
        foreach ($bookings as $booking) {
            $resourceId = $booking->getResource()->getId();
            $resourceIds[] = $resourceId;

            // Check whether the user has permission to delete the booking.
            // Note the 'calendarEvent.deleteBooking' permission will have been checked in Calendar/Event/Module.php.
            // A user can delete a booking in the resource booking section if they can access the 'resourceBooking.edit'
            // route.  If the permission check fails for any resource, stop the operation.  The user can remove the
            // offending resource from the list if he/she wants to continue.
            if (
                !$this->authorizer->isPathAuthorizedForUser(
                    'resourceBooking.edit',
                    [
                        'id' => $booking->getResource()->getId(),
                        'item' => $booking->getResource(),
                    ],
                    $this->user
                )
            ) {
                // This will occur if permissions have changed since the form page was generated.
                throw new Forbidden(
                    "You do not have permission to delete bookings for resource '{$booking->getResource()->getName()}'."
                );
            }
        }
        $resourceIds = array_unique($resourceIds); // Uniquify the array just in case duplicate values were present.

        $isDeleteFromSeries = ($efficacy === EventRecurrenceMapper::EFFICACY_SERIES && $event->getSeriesId() !== null);

        // If the user requested the booking be deleted from all events in the series (and the event is part of a series)...
        if ($isDeleteFromSeries) {
            $events = $this->eventMapper->getAllInSeries($event->getSeriesId(), futureEventsOnly: true);
        } else {
            // The event is not in a series, but treat it as a series containing one event so we don't have to deal
            // with single events separately below.
            $events = new GenericCollection(Event::class, [$event]);
        }

        // Find all bookings to delete in one query.
        // Note there is no need to repeat the permission checks for each booking, because permission depends only on
        // the resource to be booked, and the resources booked will be the same for each event in the series.
        $eventIds = $events->map(fn ($loopEvent) => $loopEvent->getId(), $events);
        $bookingIdsArrays = [];
        foreach ($resourceIds as $resourceId) {
            $bookingIdsToDeleteForThisResource = $this->bookingMapper->findIdsByResourceIdForEvents(
                $resourceId,
                $eventIds->getAll(),
                futureBookingsOnly: $isDeleteFromSeries // Only allow deletion of past bookings if updating a single booking.
            );
            $bookingIdsArrays[] = $bookingIdsToDeleteForThisResource;
        }
        // Building the array this way is more efficient than using array_merge() inside the loop above.
        $bookingIdsToDeleteForAllResources = array_merge([], ...$bookingIdsArrays);

        if (count($bookingIdsToDeleteForAllResources) === 0) {
            // This will occur if the user attempts to delete a series consisting of past bookings.
            throw new NotFound(
                'No deletable bookings found.  Note past bookings are not deletable as part of a series.'
            );
        }

        try {
            $this->bookingMapper->deleteByColumn('id', $bookingIdsToDeleteForAllResources);
        } catch (MapperException) {
            throw new SchoolboxException(
                'Could not delete booking' . ($isDeleteFromSeries ? 's from series of events' : '') . '.'
            );
        }

        return count($bookingIdsToDeleteForAllResources); // Return the number of bookings deleted.
    }


    /*
     * Change the variant of the given event from 'booking' to 'event'.
     * If the given event does not have variant 'booking', do nothing.
     *
     * @param Event $event
     * @return void
     */
    public function addToCalendar(Event $event): void
    {
        if ($event->getVariant() === Event::VARIANT_BOOKING) {
            $event->setVariant(Event::VARIANT_EVENT);

            try {
                $this->eventMapper->save($event);
            } catch (MapperException) {
                $problem = new ApiProblem('Could not save event.')
                    ->setStatus(self::HTTP_STATUS_CODE_SERVER_ERROR)
                    ->asArray();
                $this->response = $this->responseAsJson($problem)
                    ->withStatus(self::HTTP_STATUS_CODE_SERVER_ERROR);
                return;
            }

            $this->response = $this->responseAsJson(['success' => true])->withStatus(self::HTTP_STATUS_CODE_OK);
        } else {
            $this->response = $this->responseAsJson(['success' => false])->withStatus(self::HTTP_STATUS_CODE_NOT_FOUND);
        }
    }


    /**
     * For each event in $eventById, check whether adding a booking of each resource in $resourceById would result in
     * a clash with an already approved booking.  (If so, the new booking can still be made if clash bookings are
     * allowed on the resource.)
     * TODO: Move this function into a helper class.
     * @param GenericCollection<int, BookingItemModel> $resourceById - Resources indexed by resourceId.
     * @param GenericCollection<int, Event> $eventById - Events indexed by eventId.
     * @return array
     */
    private function checkForClashesIfResourceBookingsAddedToEvents(
        GenericCollection $resourceById,
        GenericCollection $eventById
    ): array {
        $isClashByResourceIdByEventId = [];

        $periodsOfApprovedBookingsByResourceId = $this->bookingMapper->getPeriodsOfFutureApprovedBookingsByResourceId(
            $resourceById->keys(),
            sortByStartDatetimeAsc: true
        );

        // For each event in the series, check for clash bookings if each resource is booked at a time matching the event time.
        /** @var Event $event */
        foreach ($eventById as $eventId => $event) {
            // For each resource, check for existing approved bookings of that resource that overlap the event period.
            foreach ($resourceById as $resourceId => $resource) {
                $periodsOfApprovedBookings = $periodsOfApprovedBookingsByResourceId[$resourceId] ?? [];
                foreach ($periodsOfApprovedBookings as $period) {
                    if ($period['startDatetime']->gt($event->getEndDatetime())) {
                        // The $periods are sorted by start datetime ascending, so we can be sure that no other periods will clash with event.
                        break;
                    }

                    if (
                        $period['startDatetime']->lt($event->getEndDatetime()) &&
                        $period['endDatetime']->gt($event->getStartDatetime())
                    ) {
                        if (!array_key_exists($eventId, $isClashByResourceIdByEventId)) {
                            $isClashByResourceIdByEventId[$eventId] = [];
                        }

                        // If $resource was booked for $event, that booking would clash with an approved booking for $period.
                        $isClashByResourceIdByEventId[$eventId][$resourceId] = true;
                    }
                }
            }
        }

        return $isClashByResourceIdByEventId;
    }

    /**
     * For each booking in $movedBookings, check whether the booking clashes with any existing approved bookings of the
     * same resource.  (If it does clash, the new booking can still be made if clash bookings are allowed on the resource.)
     * TODO: Move this function into a helper class.
     *
     * @param GenericCollection<int, BookingModel> $movedBookings
     *  - Bookings to check for clashes.  This array should contain all the bookings that are moved in $movedBookings.
     * @param GenericCollection<int, BookingItemModel> $resourceById
     *  - Resources indexed by resourceId.  This array should contain all the resources that are booked in $movedBookings.
     * @return array
     */
    private function checkForClashesIfResourceBookingsMoved(
        GenericCollection $movedBookings,
        GenericCollection $resourceById,
    ): array {
        $isClashByBookingId = [];

        $periodsOfApprovedBookingsByResourceId = $this->bookingMapper->getPeriodsOfFutureApprovedBookingsByResourceId(
            $resourceById->keys(),
            sortByStartDatetimeAsc: true,
            // Exclude the moved bookings themselves when finding periods of approved bookings that potentially clash.
            bookingIdsToExclude: $movedBookings->map(fn ($movedBooking) => $movedBooking->getId(), $movedBookings)->getAll()
        );

        // For each booking, check for clashes with existing approved bookings of the same resource.
        /** @var BookingModel $movedBooking*/
        foreach ($movedBookings as $movedBooking) {
            $isClashByBookingId[$movedBooking->getId()] = false; // Assume no clash until a clash is found.
            $periodsOfApprovedBookings = $periodsOfApprovedBookingsByResourceId[$movedBooking->getResource()->getId()] ?? [];
            foreach ($periodsOfApprovedBookings as $period) {
                if ($period['startDatetime']->gt($movedBooking->getBookingEndAt())) {
                    // The $periods are sorted by start datetime ascending,
                    // so we can be sure that no other periods will clash with $movedBooking.
                    break;
                }

                if (
                    $period['startDatetime']->lt($movedBooking->getBookingEndAt()) &&
                    $period['endDatetime']->gt($movedBooking->getBookingStartAt())
                ) {
                    // If $movedBooking was persisted, it would clash with an approved booking for $period.
                    $isClashByBookingId[$movedBooking->getId()] = true;
                }
            }
        }

        return $isClashByBookingId;
    }

    /**
     * Return the status to be set for a new booking that does not clash with any existing booking.
     * Note bookings that clash with existing bookings should be given status 'pending'.
     *
     * TODO: Modify this function so that no database operations are required.
     *       This should be done because this function is called from within a loop.
     *
     * @param BookingModel $booking
     * @return int
     */
    private function getStatusIdForNonClashBooking(BookingModel $booking): int
    {
        $resource = $booking->getResource();
        $event = $booking->getEvent();

        // What about the resource? If it's an open resource, confirm the
        // booking immediately.
        if ($resource->getModerationType()->getId() === BookingModerationTypeModel::TYPE_OPEN) {
            return BookingStatusModel::STATUS_APPROVED;
        }

        if (
            $resource->isUserModerator($this->getUser())
            && (int)$event->getAuthor()->getId() === (int)$this->getUser()->getId()
        ) {
            /* If you're a Moderator and can moderate this request,
             * and you created the booking in the first place,
             * set it to Approved - otherwise you'll end up approving your own request anyway
             */
            return BookingStatusModel::STATUS_APPROVED;
        }

        return BookingStatusModel::STATUS_PENDING;
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


    /**
     * Update all resource bookings (start / end times) attached to this event
     *
     * @param Event $event
     * @param ?Alaress_Date_Format $oldStartDateTime
     * @param ?Alaress_Date_Format $oldEndDateTime
     * @param string $efficacy
     * @return array
     * @throws MapperException
     */
    private function moveBookings(
        Event $event, ?Alaress_Date_Format $oldStartDateTime, ?Alaress_Date_Format $oldEndDateTime, string $efficacy
    ): array
    {
        // can't work out the diff if there is no past...
        if (!$oldStartDateTime || !$oldEndDateTime) {
            return []; // No bookings to move if we don't have old times
        }
        // get the time diff in minutes
        $startDiff = $oldStartDateTime->diffInMinutes($event->getStartDateTime(), false);
        $endDiff = $oldEndDateTime->diffInMinutes($event->getEndDateTime(), false);

        // get all bookings for this event
        $bookings = $this->bookingMapper->getForAllEventIds([$event->getId()]);

        // clashes?
        $validation = new Validation(
            $this->getUser(),
            $this->bookingMapper,
            $this->bookingStatusMapper,
            $this->termMapper,
            $this->externalTimetable,
            $this->campusMapper,
            $this->getConfigLoader(),
            $this->externalEventMapper,
        );

        // collect errors
        $errors = [];

        // update each booking, and check for clashes
        foreach ($bookings[$event->getId()] as $booking) {
            /** @var ResourceBookingModel $booking */

            // booking dates may be null, so fall back to event dates
            $bookingStart = $booking->getBookingStartAt() ?? $event->getStartDateTime();
            $bookingEnd = $booking->getBookingEndAt() ?? $event->getEndDateTime();
            $newStart = $bookingStart?->addMinutes($startDiff);
            $newEnd = $bookingEnd?->addMinutes($endDiff);

            $clashErrors = $validation->validateForClashes(
                $booking->getResource(),
                $newStart->getDateTimeObject(), // We need a different kind of date object.
                $newEnd->getDateTimeObject(), // We need a different kind of date object.
                $booking
            );

            if (count($clashErrors) > 0 && $validation->validationErrorsAreAllClashes($clashErrors)) {
                $errors[] = $clashErrors;
                // set status to pending if there are clashes (even if the resource can NOT have clashes...)
                $booking->setStatus($this->bookingStatusMapper->createModel(\Schoolbox\ResourceBooking\Booking\Status\Model::STATUS_PENDING));
            } else {
                // If booking is managed or moderated, and was previously approved, set status back to pending so it can be re-approved by a moderator if the time has changed.
                $moderationId = $booking->getResource()->getModerationType()->getId();
                $statusId = $booking->getStatus()->getId();
                if (
                    $booking->getResource()->isUserModerator($this->getUser())
                    && (int)$event->getAuthor()->getId() === (int)$this->getUser()->getId()
                ) {
                    /* If you're a Moderator and can moderate this request,
                     * and you created the booking in the first place,
                     * set it to Approved - otherwise you'll end up approving your own request anyway
                     */
                    $booking->setStatus($this->bookingStatusMapper->createModel(\Schoolbox\ResourceBooking\Booking\Status\Model::STATUS_APPROVED));
                } elseif (
                    ($moderationId === ResourceBookingModerationTypeModel::TYPE_MODERATED || $moderationId === ResourceBookingModerationTypeModel::TYPE_MANAGED) &&
                    $statusId === \Schoolbox\ResourceBooking\Booking\Status\Model::STATUS_APPROVED
                ) {
                    $booking->setStatus($this->bookingStatusMapper->createModel(\Schoolbox\ResourceBooking\Booking\Status\Model::STATUS_PENDING));
                }
                // if not moderated or managed, keep approved status
            }

            $booking->setBookingStartAt($newStart);
            $booking->setBookingEndAt($newEnd);
        }

        // save the lot
        $this->bookingMapper->save($bookings[$event->getId()]);

        return $errors;
    }
}
