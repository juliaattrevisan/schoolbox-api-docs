<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Event;

use Alaress\Database\Predicate\Builder;
use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Alaress_Date_Locale_Config;
use DateTimeInterface;
use External_Timetable;
use InvalidArgumentException;
use LogicException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
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
            $data['resourceBookingsCollection'] = $helper->getResourceBookingsCollection($event);
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

        $bookingErrors = [];
        if ($existingEvent) {
            // Move any resource bookings attached to this event
            $bookingErrors = $this->moveBookings($event, $oldStart, $oldEnd, $this->getUser());
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
                    'bookingErrors' => $bookingErrors,
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
        $bookingErrors = $this->moveBookings($event, $oldStart, $oldEnd, $this->getUser());

        // If there were booking errors, return them
        if (count($bookingErrors)) {
            $this->response = $this->responseAsJson(
                ['errors' => $bookingErrors],
            )->withStatus(200);
            return;
        }

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
                $this->response = $this->responseEmpty()->withStatus(204); // HTTP status 204 means 'No Content'.
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

        /** @var GenericCollection<BookingItemModel> $resourceById */
        $resourceById = $this->bookingItemMapper->findByColumn('id', $resourceIds)->indexBy('id');

        // Check whether the user has permission to book the selected resources.
        $route = 'resourceBooking.createBooking';
        foreach ($resourceById as $resourceId => $resource) {
            if (!$this->authorizer->isPathAuthorizedForUser($route, [$resourceId], $this->user)) {
                throw new SchoolboxException(
                    "You do not have permission to create bookings for resource '{$resource->getName()}'."
                );
            }
        }

        // If the user requested the booking be added to all events in the series (and the event is part of a series)...
        if ($efficacy === EventRecurrenceMapper::EFFICACY_SERIES && $event->getSeriesId() !== null) {
            // Get all future events in the series.  Note the repeat period of the series is not recorded in the database,
            // and events can be removed from a series once the series has been created.  Therefore, we cannot assume
            // any regular period between events, and so must fetch each event's start/end times.
            $pb = new Builder($this->eventMapper->getDatabase()->getQuoter(), 'event');
            $pb->equals('series_id', $event->getSeriesId());
            $pb->greaterThan('start_datetime', Alaress_Date_Format::now()->getDatabase());
            /** @var GenericCollection<Event> $eventById */
            $eventById = $this->eventMapper->findByPredicates($pb);
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

        // Create a list of booking models to be persisted depending on validation results.
        /** @var Event $loopEvent */
        foreach ($eventById as $eventId => $loopEvent) {
            $isClashByResourceId = $isClashByResourceIdByEventId[$eventId] ?? [];
            /** @var BookingItemModel $resource */
            foreach ($resourceById as $resourceId => $resource) {
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

        // Only unexpected errors require non-success code.
        // It is expected that some or all attempts to book resources may fail for validation reasons.
        $this->response = $this->responseAsJson(
            // This data structure must match type 'AddBookingsToEventResponse' defined in add-resource-bookings-modal.vue.
            [
                'nBookingsAttempted' => $nBookingsAttempted,
                'nBookingsCreatedWithClash' => $nBookingsCreatedWithClash,
                'nBookingsCreatedWithoutClash' => $nBookingsCreatedWithoutClash,
            ]
        )->withStatus($httpStatusCode);
    }


    /**
     * Process a 'save' request from the 'edit booking' modal that appears on the 'Event Details' page.
     * (Find the 'Event Details' page by clicking on a calendar event, then clicking 'Details' in the event modal.)
     * (Find the 'edit booking' modal by clicking on an 'edit' button on a resource booking.)
     * @param Event $event
     * @param $bookingId
     */
    public function editBooking(Event $event, $bookingId): void
    {
        $data = $this->request->getParsedBody();

        $startDatetimeStr = $data['startDatetime'] ?? null;
        $endDatetimeStr = $data['endDatetime'] ?? null;
        $notes = $data['notes'] ?? null;

        if ($startDatetimeStr === null || $endDatetimeStr === null || $notes === null || $bookingId === null) {
            throw new SchoolboxException('Missing required data in form submission.');
        }

        try {
            $booking = $this->bookingMapper->findById($bookingId);
        } catch (MapperException) {
            throw new SchoolboxException('Could not find booking with ID ' . $bookingId);
        }

        // Check that $bookingId is linked to $event.
        if ($booking->getEvent()->getId() !== $event->getId()) {
            throw new SchoolboxException('Booking/event mismatch when saving booking.');
        }

        $startDatetime = new Alaress_Date_Format($startDatetimeStr, Alaress_Date_Format::FORMAT_RFC3339_W3C);
        $endDatetime = new Alaress_Date_Format($endDatetimeStr, Alaress_Date_Format::FORMAT_RFC3339_W3C);

        $booking->setBookingStartAt($startDatetime);
        $booking->setBookingEndAt($endDatetime);
        $booking->setNotes($notes);

        try {
            $this->bookingMapper->save($booking);
        } catch (MapperException) {
            throw new SchoolboxException('Could not save booking.');
        }

        $this->response = $this->responseAsJson(
            // This data structure must match type 'EditBookingResponse' defined in edit-resource-booking-modal.vue.
            [
                'bookingId' => $booking->getId(),
                'startDatetime' => $booking->getBookingStartAt()?->getDatabase(),
                'endDatetime' => $booking->getBookingEndAt()?->getDatabase(),
                'notes' => $booking->getNotes(),
            ]
        )->withStatus(self::HTTP_STATUS_CODE_OK);
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
     * @param User $user
     * @return array
     * @throws MapperException
     */
    private function moveBookings(Event $event, ?Alaress_Date_Format $oldStartDateTime, ?Alaress_Date_Format $oldEndDateTime, User $user): array
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
            $user,
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
                    $booking->getResource()->isUserModerator($user)
                    && (int)$event->getAuthor()->getId() === (int)$user->getId()
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
