<?php

/**
 *
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Calendar\Event\Type\Day\Event;
use Calendar\Event\Type\EventInterface;
use Schoolbox\Calendar\Event as EventModel;
use Schoolbox\GenericCollection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\ResourceBooking\Booking\Mapper as ResourceBookingMapper;
use Schoolbox\ResourceBooking\Booking\Model as ResourceBookingModel;
use Schoolbox\User;

class Calendar extends DeprecatedModel
{
    /**
     * @var Group
     */
    private $groupHandler;

    /**
     * @var ResourceBookingMapper
     */
    private ResourceBookingMapper $resourceBookingMapper;

    /** @var string[] */
    private $colourSet;

    /**
     * @inheritDoc
     */
    public function __construct()
    {
        $this->_table = "event";
        $this->_index = "id";

        $this->groupHandler = new Group(UserAccess::SERVICE_CALENDAR);
        $this->resourceBookingMapper = ResourceBookingMapper::getInstance(); // Could not inject because this class is used in too many places.

        parent::__construct();
    }

    public function getAttendanceByEventAndUser($eventId, $userId)
    {
        $query = "
            SELECT *
            FROM event_attendance
            WHERE event_id = {$eventId}
              AND user_id = {$userId}
            LIMIT 1
        ";

        return $this->db->query($query)->fetch();
    }

    public function removeAttendanceByEvent($eventId)
    {
        return $this->db->delete('event_attendance', 'event_id', $eventId);
    }

    public function displayColour($id)
    {
        if (!is_array($this->colourSet)) {
            //build cache of colours on first call
            $query  = "SELECT eventTypeID, eventColour FROM luEventType";
            $result = $this->db->query($query);
            foreach ($result as $row) {
                $this->colourSet[$row['eventTypeID']] = $row['eventColour'];
            }
        }
        return $this->colourSet[$id] ?? '';
    }

    private function getPerm($eventID)
    {
        if (is_array($eventID)) {
            $event =& $eventID;
        } else {
            $event = $this->getByID($eventID);
        }

        // TODO remove $_SESSION dependency: or preferably this entire handler
        $sessionUser = $_SESSION['user'] ?? null;
        if (!$sessionUser instanceof User) {
            return UserAccess::ACCESS_DENY;
        }
        if ($sessionUser->isSuperuser()) {
            return UserAccess::ACCESS_FULL;
        }

        switch ($event['access']) {
            /* Allow people to post events on your behalf, such as parents, staff, superuser
             * However, this depends on the author of the event?
             * If I'm a student or parent, I only have read access to events I
             * didn't create.
             */
            case EventModel::ACCESS_PERSONAL:
                if ($event['author_id'] == $sessionUser->getId()) {
                    return UserAccess::ACCESS_WRITE;
                }
                if ($event['owner'] == $sessionUser->getId()) {
                    return $sessionUser->getRole()->isStaff()
                        ? UserAccess::ACCESS_WRITE
                        : UserAccess::ACCESS_READ;
                }
                return UserAccess::ACCESS_DENY;

            case EventModel::ACCESS_GROUP:
                // Need to check all groups, not just one.
                if (isset($event['categoryList']) && is_array($event['categoryList'])) {
                    $categoryList = $event['categoryList'];
                } else {
                    $categoryList = $this->getEventCategory((int)$event['id']);
                }

                $this->groupHandler->setService(UserAccess::SERVICE_HOMEPAGE);
                $permissions = [];
                foreach ($categoryList as $category) {
                    $permissions[] = $this->groupHandler->getPermission($category);
                }
                $this->groupHandler->setService(UserAccess::SERVICE_CALENDAR);

                if (empty($permissions)) {
                    $permissions[] = UserAccess::ACCESS_DENY;
                }
                return min($permissions);

            case EventModel::ACCESS_CAMPUS:
                $campusList = null;
                if (is_array($event['campusList'])) {
                    $campusList = $event['campusList'];
                } elseif ($event['id']) {
                    $campusList = $this->getEventCampus($event['id']);
                }

                /* Find the highest permission level
                 */
                $output = UserAccess::ACCESS_DENY;

                if (is_array($campusList)) {
                    foreach ($campusList as $campus) {
                        $permission = $this->groupHandler->getPermission($campus);
                        if ($output < $permission || !$output) {
                            $output = $permission;
                        }
                    }
                }

                return $output;

            default:
                return UserAccess::ACCESS_DENY;
        }
    }

    public function getTypeList()
    {
        $query = "SELECT eventTypeID, eventType, eventColour FROM luEventType WHERE deleted_at IS NULL ORDER BY sequence ASC";
        return $this->db->query($query)->fetchAll();
    }


    /**
     * Get an event by its id
     *
     * @param int $eventID
     *
     * @return array|bool
     */
    public function getByID($eventID)
    {
        $query = "
			SELECT
				e.id, e.series_id, e.owner, e.author_id, e.access, e.type, e.title, e.start_datetime, e.end_datetime,
				e.location, e.attendance, e.detail, e.publish_date, e.allday,
				DATE_FORMAT(e.start_datetime, '" . Config::get('calendar_time_format') . "') startTime,
				DATE_FORMAT(e.end_datetime, '" . Config::get('calendar_time_format') . "') endTime,
				u.title AS userTitle, u.firstname, u.preferred_name, u.lastname
			FROM {$this->_table} e
			LEFT JOIN user u ON u.id = e.owner
			WHERE e.{$this->_index} = $eventID
		";

        return $this->db->query($query)->fetch();
    }

    /**
     * Get all events occurring across the specified date span.
     *
     * @param User     $user
     * @param User     $viewer
     * @param int|null $startTimestamp           If not null, filter out events that end on
     *                                        or before this time
     *                                        (end times are exclusive in calendars)
     * @param int|null $endTimestamp             If not null, filter out events that start
     *                                        after this time
     * @param array $filter                   A filter to put on the list of results.
     *                                        * campus: if set, event set is restricted to
     *                                        events in this campus.
     *                                        * group: if set, event set is restricted to
     *                                        events for this group.
     *                                        * personal: if set, only personal events for
     *                                        current user are set.
     *                                        * id: if set, only this event ID will be retrieved.
     *                                        If empty, all events for user are retrieved.
     *                                        * type: if set, only events of this type will be retrieved.
     *                                        * allowResources: if set to true, resource booking events will be included.
     * @return GenericCollection<EventInterface>
     */
    public function getDayEvents(User $user, User $viewer, $startTimestamp, $endTimestamp, $filter = [])
    {
        // If campus is NOT provided in $filter or the viewer is not staff, we limit events by campuses which the user has access
        $campusList = [];
        if (!isset($filter['campus']) || !$viewer->getRole()->isStaff()) {
            $campusList = $user->getCampusCollection()->getPropertyValues('id');

            //Fake in some campusii here from calendar permissions, so user can see events relevant
            $accessMapper = UserAccessMapper::getInstance();

            $calendarPerms = $accessMapper->findByColumnList(
                [
                    'uid'     => $user->getId(),
                    'service' => UserAccess::SERVICE_CALENDAR,
                ]
            )->getPropertyValues('ServiceIdentifier');
            $campusList    = array_merge($campusList, $calendarPerms);
        }

        if (count($campusList) > 0) {
            $campusQuery = "event_campus.campus_id IN(" . implode(',', $campusList) . ")";
        } else {
            $campusQuery = '1 = 1';
        }

        /* get accessible groups
         */
        $groupList  = [];
        $groupQuery = '';

        $this->groupHandler->setService(UserAccess::SERVICE_HOMEPAGE);
        $tempList = $this->groupHandler->involvedInCategoryList($user->getId());

        if (is_array($tempList)) {
            foreach ($tempList as $temp) {
                $groupList[] = $temp['id'];
            }

            // Ensure that requested group is included in filter
            // (as long as user has at least read permission on that group)
            if (isset($filter['group'])) {
                if ($this->groupHandler->getPermission($filter['group'], $user->getId()) >= UserAccess::ACCESS_READ) {
                    $groupList[] = intval($filter['group']);
                }
            }

            /* If there are no groups prevent sql error
             */
            if (count($groupList)) {
                $groupQuery = "OR (access = " . EventModel::ACCESS_GROUP . " AND event_folder.folder_id IN (" . implode(
                    ',',
                    $groupList
                ) . "))";
            }
        }

        $this->groupHandler->setService(UserAccess::SERVICE_CALENDAR);

        /* Give people with userAccess to moderate Calendar Access to view all Campus Posts
         * This check is done against the calendar user, not the logged-in user,
         * because we don't want to pollute a user's calendar if viewing it as
         * an admin.
         */
        $groupChunk = "AND event_role.role_id = {$user->getRole()->getId()}";
        if ($user->getCampusCollection()->count()) {
            if ($this->groupHandler->getPermission($user->getCampusCollection()->first()->getId(), $user->getId()) > UserAccess::ACCESS_WRITE) {
                $groupChunk = "";
            }
        }

        //
        // Filters - some of these are covered by main query anyway, so no need
        // for extra filters.
        $filter_query = [];
        $filterParams = [];

        if (isset($filter['campus'])) {
            $filter_query[] = 'access = ' . EventModel::ACCESS_CAMPUS;
            $filter_query[] = 'event_campus.campus_id = ' . intval($filter['campus']);
        }

        if (isset($filter['group'])) {
            $filter_query[] = 'access = ' . EventModel::ACCESS_GROUP;
            $filter_query[] = 'event_folder.folder_id = ' . intval($filter['group']);
        }

        if (isset($filter['type'])) {
            $filter_query[] = 'type = ' . intval($filter['type']);
        }

        /* Filter to specific event types
         * TODO: Replace injections with something less injecty (rewrite in prepared statements)
         */
        if (isset($filter['typeSet']) && is_array($filter['typeSet']) && count($filter['typeSet']) > 0) {
            $filter_query[] = 'type IN (' . implode(',', array_map('intval', $filter['typeSet'])) . ')';
        }

        if (isset($filter['personal'])) {
            $filter_query[] = 'access = ' . EventModel::ACCESS_PERSONAL;
            $filter_query[] = 'event_user.user_id = ' . intval($filter['personal']);
        }

        if (isset($filter['id'])) {
            if (is_array($filter['id']) && !empty($filter['id'])) {
                $idBinds = \Alaress\Database\Predicate\Builder::getBindings($filter['id'], 'id');
                $filter_query[] = "event.id IN ({$idBinds->getPlaceholder()})";
                $filterParams = array_merge($filterParams, $idBinds->getParams());
            } else {
                $filter_query[] = 'event.id = ' . intval($filter['id']);
            }
        }

        // default to hide resource bookings unless explicitly allowed
        if (true !== ($filter['allowResources'] ?? false)) {
            $filter_query[] = "event.variant != '" . EventModel::VARIANT_BOOKING . "'";
        }

        $filter_query = implode(' AND ', $filter_query);

        if (strlen($filter_query) > 0) {
            $filter_query = " AND ($filter_query)";
        }

        /* If you've been explicitly invited to this event, show it regardless of status
         *
         * Status is the response from the invitee so they should still have an opportunity
         * to change that response if they want
         */
        $attendance_query = "OR (event_attendance.user_id = '{$user->getId()}')";

        $access_query = 'AND (
            (access = ' . EventModel::ACCESS_PERSONAL . " AND (owner = '{$user->getId()}' OR event_user.user_id = '{$user->getId()}'))
            $groupQuery
            OR (access = " . EventModel::ACCESS_CAMPUS . " AND $campusQuery $groupChunk OR owner = '{$user->getId()}')
            $attendance_query
            )";


        // Build the date query
        $dateQuery      = '';
        $dateQueryParts = [];
        if (isset($startTimestamp)) {
            // Start timestamp: don't get events which end before or on that time
            $adf              = Alaress_Date_Format::newFromTimestamp($startTimestamp);
            $dateQueryParts[] = sprintf(
                "event.end_datetime < '%s'",
                $adf->getUsingFormat(Alaress_Date_Format::DATABASE_FORMAT)
            );
        }
        if (isset($endTimestamp)) {
            // End timestamp: don't get events which start after that time
            $adf              = Alaress_Date_Format::newFromTimestamp($endTimestamp);
            $dateQueryParts[] = sprintf(
                "event.start_datetime >= '%s'",
                $adf->getUsingFormat(Alaress_Date_Format::DATABASE_FORMAT)
            );
        }
        if (!empty($dateQueryParts)) {
            $dateQuery = "AND (NOT (" . implode(' OR ', $dateQueryParts) . '))';
        }

        // get single events for the day
        // Don't get events for resources, get them separately.
        $query = sprintf(
            "
            SELECT DISTINCT
                event.id,
                event.series_id,
                event.owner,
                GROUP_CONCAT(DISTINCT event_campus.campus_id) as campusList,
                event.start_datetime as start_datetime,
                event.end_datetime as end_datetime,
                allday,
                YEAR(event.start_datetime) AS year,
                type,
                event.title,
                access,
                location,
                detail,
                attendance,
                max_attendees,
                user.id as uid,
                user.title as userTitle,
                firstname,
                preferred_name,
                lastname,
                eventType,
                event.variant AS eventVariant,
                eventColour,
                event.publish_date,
                folder.id AS categoryId,
                folder.name AS categoryName,
                event.completed,
                author_id,
                if (user.pronoun, concat('(', pronoun.subjective, '/', pronoun.objective, ')'), '') as 'pronoun'
            FROM event
            LEFT JOIN user             ON event.author_id = user.id
            LEFT JOIN luEventType      ON type = eventTypeID
            LEFT JOIN resource_booking ON event.id = resource_booking.event_id
            LEFT JOIN event_user       ON event.id = event_user.event_id     AND access = %d
            LEFT JOIN event_folder     ON event.id = event_folder.event_id   AND access = %d
            LEFT JOIN folder           ON event_folder.folder_id = folder.id AND event_folder.folder_id IS NOT NULL
            LEFT JOIN event_role       ON event.id = event_role.event_id     AND access = %d
            LEFT JOIN event_campus     ON event.id = event_campus.event_id   AND access = %d
            LEFT JOIN event_attendance ON event.id = event_attendance.event_id
            LEFT JOIN pronoun ON user.pronoun = pronoun.id
            WHERE
                event.deleted_at is NULL
                AND folder.deleted_at IS NULL
                {$access_query}
                {$dateQuery}
                {$filter_query}
            GROUP BY id
            ORDER BY
                event.start_datetime, event.end_datetime
        ",
            EventModel::ACCESS_PERSONAL,
            EventModel::ACCESS_GROUP,
            EventModel::ACCESS_CAMPUS,
            EventModel::ACCESS_CAMPUS
        );

        $allEvents = $this->db->query($query, $filterParams)->fetchAll();

        /* Standardised output
         */
        $stack = new GenericCollection(EventInterface::class);

        if (is_array($allEvents) && count($allEvents)) {
            // Get linked ResourceBookingModels for all events.
            // Get them all in one query outside the loop below to minimise the number of database queries.
            $allEventIds = array_map(static fn ($currentEvent) => $currentEvent['id'], $allEvents);
            /** @var array<int, GenericCollection<ResourceBookingModel>> $resourceBookingModelsByEventId */
            $resourceBookingModelsByEventId = $this->resourceBookingMapper->getForAllEventIds($allEventIds);

            foreach ($allEvents as $currentEvent) {
                $event = new Event();

                $event->setAuthorName(
                    (new User\Formatter\Factory())->buildFromNothing()->formatArray(
                        ['title' => ''] + $currentEvent
                    )
                );
                $event->setAuthorId($currentEvent['uid']);
                $event->setEventId($currentEvent['id']);
                $event->setStartTime(
                    new Alaress_Date_Format($currentEvent['start_datetime'], Alaress_Date_Format::DATABASE_FORMAT)
                );
                $event->setEndTime(
                    new Alaress_Date_Format($currentEvent['end_datetime'], Alaress_Date_Format::DATABASE_FORMAT)
                );
                $event->setType($currentEvent['type']);
                $event->setTitle($currentEvent['title']);
                $event->setAccess($currentEvent['access']);
                $event->setEventType($currentEvent['eventType']);
                $event->setEventVariant($currentEvent['eventVariant']);
                $event->setColour($currentEvent['eventColour']);
                $event->setDetail($currentEvent['detail']);
                $event->setLocation($currentEvent['location']);
                $event->setPublishDate(
                    empty($currentEvent['publish_date'])
                        ? null
                        : new Alaress_Date_Format($currentEvent['publish_date'], Alaress_Date_Format::DATABASE_FORMAT)
                );
                $event->setAllDay(!empty($currentEvent['allday']));
                $event->setSeriesId($currentEvent['series_id']);
                $event->setBookingCollection($resourceBookingModelsByEventId[$currentEvent['id']]);

                /* If you have write access to the event
                 */
                $eventPermission = $this->getPerm($currentEvent);

                if ($eventPermission > UserAccess::ACCESS_READ) {
                    $event->setIsEditable(true);
                } else {
                    $event->setIsEditable(false);
                }

                if ($currentEvent['access'] == EventModel::ACCESS_GROUP) {
                    //TODO: refactor this so its no so resource intensive, we only need a list of IDs
                    $mapper       = new \Schoolbox\Calendar\Relation\CategoryMapper();
                    $allRelations = $mapper->findByColumn('event_id', $currentEvent['id']);
                    $event->setCategoryCollection($allRelations);
                } elseif ($currentEvent['access'] == EventModel::ACCESS_CAMPUS) {
                    $event->setCampusList($currentEvent['campusList']);
                }

                $event->setAttendance($currentEvent['attendance']);
                $event->setMaxAttendees($currentEvent['max_attendees']);

                $event->setCompleted((bool)$currentEvent['completed']);

                /* Only set path if user has read access. setPath() doesn't accept
                 * and the frontend uses a falsey check, so empty string is used when access is denied.
                 */
                $event->setPath(
                    $eventPermission >= UserAccess::ACCESS_READ
                        ? '/calendar/event/' . $currentEvent['id']
                        : ''
                );

                $isPublished     = $event->isPublishedAtDate(Alaress_Date_Format::now());
                $isAlwaysVisible = $viewer->isSuperuser() || $event->isOwner($viewer->getId()) || $event->isEditable();

                if ($isAlwaysVisible || $isPublished) {
                    $stack->add($event);
                }
            }
        }

        return $stack;
    }


    /**
     * Gets all event groups for the given event ID.
     *
     * @param int $eventID
     *
     * @return array
     */
    public function getEventGroup($eventID)
    {
        $output = [];
        $query  = "SELECT role_id FROM event_role WHERE event_id = " . intval($eventID);
        $result = $this->db->query($query);
        foreach ($result as $event) {
            $output[] = $event['group_id'];
        }
        return $output;
    }

    /**
     * Gets all event campuses for the given event ID.
     *
     * @param int $eventID
     *
     * @return array
     */
    public function getEventCampus($eventID)
    {
        $output = [];
        $query  = "SELECT campus_id FROM event_campus WHERE event_id = " . intval($eventID);
        $result = $this->db->query($query);
        foreach ($result as $event) {
            $output[] = $event['campus_id'];
        }
        return $output;
    }

    /**
     * Gets all event categories for the given event ID.
     *
     * @param int $eventId
     *
     * @return array
     */
    public function getEventCategory(int $eventId): array
    {
        $output = [];
        $query  = "SELECT folder_id
                    FROM event_folder JOIN folder ON event_folder.folder_id = folder.id
                    WHERE event_id = {$eventId}
                    AND folder.deleted_at IS NULL";
        $result = $this->db->query($query);
        foreach ($result as $event) {
            $output[] = $event['folder_id'];
        }
        return $output;
    }

    private function createGroups($groupList, $eventID)
    {
        if (is_array($groupList)) {
            foreach ($groupList as $groupID) {
                $eventGroup = [
                    'event_id' => $eventID,
                    'role_id'  => $groupID,
                ];
                $this->db->insert('event_role', $eventGroup);
            }
        }
    }

    private function createCampus($campusList, $eventID)
    {
        if (is_array($campusList)) {
            foreach ($campusList as $campusID) {
                $eventCampus = [
                    'event_id'  => $eventID,
                    'campus_id' => $campusID,
                ];
                $this->db->insert('event_campus', $eventCampus);
            }
        }
    }

    private function delPerms($eventID)
    {
        $this->db->delete('event_role', 'event_id', $eventID);
        $this->db->delete('event_campus', 'event_id', $eventID);
    }

    public function create($event)
    {
        if ($this->getPerm($event) < UserAccess::ACCESS_WRITE) {
            error(35);
        }

        $groupList  = $event['groupList'];
        $campusList = $event['campusList'];

        unset($event['groupList']);
        unset($event['campusList']);

        $eventID = parent::create($event);

        $this->createGroups($groupList, $eventID);
        $this->createCampus($campusList, $eventID);

        return $eventID;
    }

    public function update($event)
    {
        $cleanEvent = $this->getByID($event['id']);

        /* Check perms on existing event */
        if ($this->getPerm($event['id']) < UserAccess::ACCESS_WRITE) {
            error(34);
        }

        /* Check perms on what we want the event to become */
        if ($this->getPerm($event) < UserAccess::ACCESS_WRITE) {
            error(34);
        }

        $groupList  = $event['groupList'] ?? null;
        $campusList = $event['campusList'] ?? null;

        unset($event['groupList']);
        unset($event['campusList']);

        /* Clear attendance if it has changed from true to false */
        if (($event['attendance'] ?? null) < $cleanEvent['attendance']) {
            $this->removeAttendanceByEvent($event['id']);
        }

        parent::update($event);

        $this->delPerms($event['id']);

        $this->createGroups($groupList, $event['id']);
        $this->createCampus($campusList, $event['id']);

        return $event['id'];
    }

    public function delete($eventID)
    {
        $event = $this->getByID($eventID);

        if ($this->getPerm($eventID) < UserAccess::ACCESS_WRITE) {
            error(34);
        }

        $this->removeAttendanceByEvent($eventID);

        $deleted = parent::delete($eventID);
        $this->delPerms($eventID);

        return $deleted;
    }
}
