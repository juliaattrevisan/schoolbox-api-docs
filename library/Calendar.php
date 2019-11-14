<?php

use Calendar\Event\Collection;
use Calendar\Event\Type\Day\Event;
use Schoolbox\Calendar\Event as EventModel;
use Schoolbox\Permission\Access\UserAccess;

class Calendar extends DeprecatedModel
{
    /**
     * @var Group
     */
    private $groupHandler;

    /** @var string[] */
    private $colourSet;

    function __construct()
    {

        $this->_table = "event";
        $this->_index = "id";

        $this->groupHandler = new Group(UserAccess::SERVICE_CALENDAR);

        parent::__construct();
    }

    /**
     * Get a list of upcoming events with a summary tally of attendances
     * for both Pending and Approved statuses
     *
     * @return array|bool
     */
    public function getUpcomingEventAttendance()
    {
        $query = "
			SELECT
				event.id,
				event.title,
				event.start_datetime,
				event.end_datetime,
				event.location,
				event.owner,
				user.firstname,
				user.lastname,
				SUM(IF(event_attendance.status = 1, 1, 0)) AS numPending,
				SUM(IF(event_attendance.status = 2, 1, 0)) AS numApproved
			FROM event
			LEFT JOIN event_attendance ON event.id = event_attendance.event_id
			LEFT JOIN `user` ON event.owner = user.id
			WHERE attendance = 1
			AND event.deleted_at IS NULL
			AND publish_date < NOW()
			GROUP BY event.id
			ORDER BY event.start_datetime DESC
		";

        return $this->db->getAll($query);
    }

    /**
     * Get a list of people who are attending/pending for a particular
     * event attendance
     *
     * @param $eventId
     * @param $status
     *
     * @return mixed
     */
    private function getByStatus($eventId, $status)
    {
        $query = "
			SELECT
				user.*,
				IF(user.local_email = 0, user.alt_email, user.local_email) AS email,
				groups.name as groupName,
				ea.id, ea.status, ea.intention
			FROM event_attendance ea
			LEFT JOIN `user` ON `user`.id = ea.user_id
			LEFT JOIN `groups` ON `user`.group_id = `groups`.id
			WHERE ea.event_id = {$eventId}
			AND ea.status = {$status}
		";

        return $this->db->getAll($query);
    }

    /**
     * Get all the people pending for an event
     *
     * @param $eventId
     *
     * @return mixed
     */
    public function getPending($eventId)
    {
        return $this->getByStatus($eventId, EventModel::ATTENDANCE_STATUS_PENDING);
    }

    /**
     * Get all the people approved for an event
     *
     * @param $eventId
     *
     * @return mixed
     */
    public function getAttending($eventId)
    {
        return $this->getByStatus($eventId, EventModel::ATTENDANCE_STATUS_APPROVED);
    }

    /**
     * Get all the people approved for an event
     *
     * @param $eventId
     *
     * @return mixed
     */
    public function getRejected($eventId)
    {
        return $this->getByStatus($eventId, EventModel::ATTENDANCE_STATUS_REJECTED);
    }

    public function getAttendeeList($eventId)
    {
        $query = "
            SELECT u.*, g.name as groupName,
                ea.id, ea.user_id, ea.status, ea.intention
            FROM event_attendance ea
            JOIN `user` u ON u.id = ea.user_id
            JOIN `groups` g ON g.id = u.group_id
            WHERE ea.event_id = '{$eventId}'
        ";

        return $this->db->getAll($query);
    }

    public function getAttendance($attendanceId)
    {
        $query = "
            SELECT *
            FROM event_attendance
            WHERE id = {$attendanceId}
            LIMIT 1
        ";

        return $this->db->getRow($query);
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

        return $this->db->getRow($query);
    }

    public function updateAttendance($attendance)
    {
        return $this->db->update('event_attendance', 'id', $attendance);
    }

    public function removeAttendanceByEvent($eventId)
    {
        return $this->db->delete('event_attendance', 'event_id', $eventId);
    }

    public function removeAttendance($attendanceId)
    {
        return $this->db->delete('event_attendance', 'id', $attendanceId);
    }

    function displayColour($id)
    {
        if (!is_array($this->colourSet)) {
            //build cache of colours on first call
            $query  = "SELECT eventTypeID, eventColour FROM luEventType";
            $result = $this->db->query($query);
            while ($row = $this->db->getNextArray($result)) {
                $this->colourSet[$row['eventTypeID']] = $row['eventColour'];
            }
        }
        return $this->colourSet[$id] ?? '';
    }

    function getPerm($eventID)
    {

        if (is_array($eventID)) {
            $event =& $eventID;
        } else {
            $event = $this->getByID($eventID);
        }

        if ($_SESSION['su']) {
            return 3;
        }

        switch ($event['access']) {

            /* Allow people to post events on your behalf, such as parents, staff, superuser
             * However, this depends on the author of the event?
             * If I'm a student or parent, I only have read access to events I
             * didn't create.
             */
            case EventModel::ACCESS_PERSONAL:
                if ($event['author_id'] == $_SESSION['id']) {
                    return 2;
                }
                if ($event['owner'] == $_SESSION['id']) {
                    return $_SESSION['user']->getRole()->getType() === 'staff' ? 2 : 1;
                }
                return 0;

            case EventModel::ACCESS_GROUP:
                // Need to check all groups, not just one.
                if (is_array($event['categoryList'])) {
                    $categoryList = $event['categoryList'];
                } else {
                    $categoryList = $this->getEventCategory($event['id']);
                }

                $this->groupHandler->setService(UserAccess::SERVICE_HOMEPAGE);
                $permissions = array();
                foreach ($categoryList as $category) {
                    $permissions[] = $this->groupHandler->getPermission($category);
                }
                $this->groupHandler->setService(UserAccess::SERVICE_CALENDAR);

                if (empty($permissions)) {
                    $permissions[] = 0;
                }
                return min($permissions);

            case EventModel::ACCESS_CAMPUS:

                if (is_array($event['campusList'])) {
                    $campusList = $event['campusList'];
                } elseif ($event['id']) {
                    $campusList = $this->getEventCampus($event['id']);
                }

                /* Find the highest permission level
                 */
                $output = 0;

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
                return 0;
        }
    }

    function getTypeList()
    {
        $query = "SELECT eventTypeID, eventType, eventColour FROM luEventType";
        return $this->db->getAll($query);
    }


    /**
     * Used on the Calendar screens to filter events
     *
     * @param $userId
     *
     * @return array
     */
    public function getFilterCampusList($userId)
    {
        $query = "
			SELECT id, name
			FROM luCampus
		";

        if (!$_SESSION['su']) {
            $query .= "
				LEFT JOIN userCampus ON userCampus.campus = luCampus.id
				WHERE userCampus.uid = '$userId'
			";
        }

        $allCampuses = $this->db->getAll($query);

        if (is_array($allCampuses) && count($allCampuses)) {
            foreach ($allCampuses as $currentCampus) {
                $output[] = array(
                    'CAMPUS_ID'   => $currentCampus['id'],
                    'CAMPUS_NAME' => $currentCampus['name'],
                );
            }
        } else {
            $output = array();
        }

        return $output;
    }



    /**
     * Get an event by its id
     *
     * @param $eventID
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
				u.title AS userTitle, u.firstname, u.lastname
			FROM {$this->_table} e
			LEFT JOIN user u ON u.id = e.owner
			WHERE e.{$this->_index} = $eventID
		";

        return $this->db->getRow($query);
    }

    function search($keyword, $offset = 0, $limit = 20, &$count = null, $sort_by = 'diff', $sort_dir = 'desc')
    {
        $keyword = $this->db->quote($keyword);

        if (is_array($_SESSION['campus'])) {
            $campusList = $_SESSION['campus'];

            $campusQuery = "event_campus.campus_id IN(" . implode(',', $campusList) . ")";
        } else {
            $campusList = array(1);

            $campusQuery = '1 = 1';
        }

        // get accessible groups
        $groupList  = array();
        $groupQuery = '';

        $this->groupHandler->setService(UserAccess::SERVICE_HOMEPAGE);
        $tempList = $this->groupHandler->involvedInCategoryList($_SESSION['id']);

        if (is_array($tempList)) {
            foreach ($tempList as $temp) {
                $groupList[] = $temp['id'];
            }

            /* If there are no groups prevent sql error
             */
            if (count($groupList)) {
                $groupQuery = "OR (access = '2' AND event_folder.category_id IN (" . implode(',', $groupList) . "))";
            }
        }

        if ($sort_by == 'diff') {
            $sort_by  = 'ABS( DATEDIFF( start_datetime, NOW() ) )';
            $sort_dir = '';
        }

        $this->groupHandler->setService(UserAccess::SERVICE_CALENDAR);

        //give people with userAccess to moderate Calendar Access to view all Camups Posts
        if (is_array($_SESSION['campus'])) {
            if ($this->groupHandler->getPermission($_SESSION['campus'][0]) > UserAccess::ACCESS_WRITE) {
                $groupChunk = "";
            } else {
                $groupChunk = "AND event_group.group_id = {$_SESSION['gid']}";
            }
        }

        $event_query = "SELECT DISTINCT event.id, owner,
                            GROUP_CONCAT(DISTINCT event_campus.campus_id) as campusList,
                            TIME_FORMAT(start_datetime, '" . Config::get('calendar_time_format') . "') as startTime,
                            TIME_FORMAT(end_datetime, '" . Config::get('calendar_time_format') . "') as endTime,
                            TIME_TO_SEC(start_datetime) as startTimestamp,
                            TIME_TO_SEC(end_datetime) as endTimestamp,
                            start_datetime, end_datetime,
                            type, event.title, access, location, detail,
                            user.id as uid, user.title AS userTitle, firstname, lastname,
                            eventType, eventColour,
                            MATCH (`event`.`title`, `detail`, `location`) AGAINST ('$keyword') AS `score`
                        FROM event
                        LEFT JOIN user ON owner = user.id
                        LEFT JOIN luEventType ON type = eventTypeID
                        LEFT JOIN event_campus ON event.id = event_campus.event_id
                        LEFT JOIN event_group ON event.id = event_group.event_id
                        LEFT JOIN event_folder ON event.id = event_folder.event_id
                        WHERE 
                             (    (access = '1' AND owner = '" . $_SESSION['id'] . "')
                                  $groupQuery
                                  OR (access = '3' AND $campusQuery $groupChunk OR owner = '" . $_SESSION['id'] . "')
                                )
                            AND MATCH (`event`.`title`, `detail`, `location`) AGAINST ('$keyword')
                            AND ( start_datetime >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND start_datetime <= DATE_ADD(NOW(), INTERVAL 18 MONTH) )
                            AND event.deleted_at IS NULL
                        GROUP BY id
                        ORDER BY $sort_by $sort_dir
                        LIMIT $offset, $limit";


        $count_query = "SELECT COUNT(DISTINCT event.id) AS event_count
						FROM event
						LEFT JOIN user ON owner = user.id
						LEFT JOIN luEventType ON type = eventTypeID
						LEFT JOIN event_campus ON event.id = event_campus.event_id
						LEFT JOIN event_group ON event.id = event_group.event_id
						LEFT JOIN event_folder ON event.id = event_folder.event_id
						WHERE (
							(access = '1' AND owner = '" . $_SESSION['id'] . "')
							$groupQuery
							OR (access = '3' AND $campusQuery $groupChunk OR owner = '" . $_SESSION['id'] . "')
						)
						AND event.deleted_at IS NULL
						AND MATCH (`event`.`title`, `detail`, `location`) AGAINST ('$keyword')
						AND ( start_datetime >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND start_datetime <= DATE_ADD(NOW(), INTERVAL 18 MONTH) )
						";

        $events = $this->db->getAll($event_query);
        $count  = $this->db->getOne($count_query);

        return $events;
    }

    /**
     * Get all events occurring across the specified date span.
     *
     * @param \Schoolbox\User $user
     * @param \Schoolbox\User $viewer
     * @param int             $startTimestamp If not null, filter out events that end on
     *                                        or before this time
     *                                        (end times are exclusive in calendars)
     * @param int             $endTimestamp   If not null, filter out events that start
     *                                        after this time
     * @param array           $filter A filter to put on the list of results.
     *                                * campus: if set, event set is restricted to
     *                                events in this campus.
     *                                * group: if set, event set is restricted to
     *                                events for this group.
     *                                * personal: if set, only personal events for
     *                                current user are set.
     *                                * id: if set, only this event ID will be retrieved.
     *                                If empty, all events for user are retrieved.
     *                                * type: if set, only events of this type will be retrieved.
     * @return Collection
     */
    public function getDayEvents(\Schoolbox\User $user, \Schoolbox\User $viewer, $startTimestamp, $endTimestamp, $filter = array())
    {
        $firstCampus = false;

        $campusList = array();

        if ($user->getCampusCollection()->count()) {
            foreach ($user->getCampusCollection() as $currentCampus) {
                if (!$firstCampus) {
                    $firstCampus = $currentCampus;
                }

                $campusList[] = $currentCampus->getId();
            }


        }

        //Fake in some campusii here from calendar permissions, so user can see events relevant
        $accessMapper = \Schoolbox\Permission\Access\UserAccessMapper::getInstance();

        $calendarPerms = $accessMapper->findByColumnList(['uid' => $user->getId(), 'service' => \Schoolbox\Permission\Access\UserAccess::SERVICE_CALENDAR])->getPropertyValues('ServiceIdentifier');
        $campusList = array_merge($campusList, $calendarPerms);

        if(count($campusList) > 0){
            $campusQuery = "event_campus.campus_id IN(" . implode(',', $campusList) . ")";
        } else {
            $campusQuery = '1 = 1';
        }

        /* get accessible groups
         */
        $groupList = array();
        $groupQuery = '';

        $this->groupHandler->setService(UserAccess::SERVICE_HOMEPAGE);
        $tempList = $this->groupHandler->involvedInCategoryList($user->getId());

        if (is_array($tempList)) {
            foreach ($tempList as $temp) {
                $groupList[] = $temp['id'];
            }

            // Ensure that requested group is included in filter
            // (as long as user has at least read permission on that group)
            if ($filter['group']) {
                if ($this->groupHandler->getPermission($filter['group'], $user->getId()) >= UserAccess::ACCESS_READ) {
                    $groupList[] = intval($filter['group']);
                }
            }

            /* If there are no groups prevent sql error
             */
            if (count($groupList)) {
                $groupQuery = "OR (access = " . EventModel::ACCESS_GROUP . " AND event_folder.category_id IN (" . implode(
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
        if ($user->getCampusCollection()->count()) {
            if ($this->groupHandler->getPermission($firstCampus->getId(), $user->getId()) > UserAccess::ACCESS_WRITE) {
                $groupChunk = "";
            } else {
                $groupChunk = "AND event_group.group_id = {$user->getRole()->getId()}";
            }
        }

        //
        // Filters - some of these are covered by main query anyway, so no need
        // for extra filters.
        $filter_query = array();

        if ($filter['campus']) {
            $filter_query[] = 'access = ' . EventModel::ACCESS_CAMPUS;
            $filter_query[] = 'event_campus.campus_id = ' . intval($filter['campus']);
        }

        if ($filter['group']) {
            $filter_query[] = 'access = ' . EventModel::ACCESS_GROUP;
            $filter_query[] = 'event_folder.category_id = ' . intval($filter['group']);
        }

        if ($filter['type']) {
            $filter_query[] = 'type = ' . intval($filter['type']);
        }

        if ($filter['personal']) {
            $filter_query[] = 'access = ' . EventModel::ACCESS_PERSONAL;
            $filter_query[] = 'event_user.user_id = ' . intval($filter['personal']);
        }

        if ($filter['id']) {
            $filter_query[] = 'event.id = ' . intval($filter['id']);
        }

        $filter_query = implode(' AND ', $filter_query);

        if (strlen($filter_query) > 0) {
            $filter_query = " AND ($filter_query)";
        }

        $attendance_query = "OR (event_attendance.user_id = '{$user->getId()}' 
            AND event_attendance.status = '". EventModel::ATTENDANCE_STATUS_APPROVED ."' )";

        $access_query = "AND (
            (access = " . EventModel::ACCESS_PERSONAL . " AND owner = '{$user->getId()}')
            $groupQuery
            OR (access = " . EventModel::ACCESS_CAMPUS . " AND $campusQuery $groupChunk OR owner = '{$user->getId()}')
            $attendance_query
            )";


        // Build the date query
        $dateQuery = '';
        $dateQueryParts = array();
        if (isset($startTimestamp)) {
            // Start timestamp: don't get events which end before or on that time
            $adf              = Alaress_Date_Format::newFromTimestamp($startTimestamp);
            $dateQueryParts[] = sprintf(
                "end_datetime < '%s'",
                $adf->getUsingFormat(Alaress_Date_Format::DATABASE_FORMAT)
            );
        }
        if (isset($endTimestamp)) {
            // End timestamp: don't get events which start after that time
            $adf              = Alaress_Date_Format::newFromTimestamp($endTimestamp);
            $dateQueryParts[] = sprintf(
                "start_datetime >= '%s'",
                $adf->getUsingFormat(Alaress_Date_Format::DATABASE_FORMAT)
            );
        }
        if (!empty($dateQueryParts)) {
            $dateQuery = "AND (NOT (".implode(' OR ', $dateQueryParts).'))';
        }

        // get single events for the day
        // Don't get events for resources, get them separately.
        $query = "
			SELECT DISTINCT event.id, event.series_id, event.owner,
				GROUP_CONCAT(DISTINCT event_campus.campus_id) as campusList,
				start_datetime, end_datetime, allday,
				YEAR(event.start_datetime) AS year,
				type, event.title, access, location, detail, attendance,
				user.id as uid, user.title as userTitle, firstname, lastname,
				eventType, eventColour,
				event.publish_date,
				folder.id AS categoryId, folder.name AS categoryName,
				event.completed, author_id
			FROM event
			LEFT JOIN user ON event.author_id = user.id
			LEFT JOIN luEventType ON type = eventTypeID
			LEFT JOIN resource_booking ON event.id = resource_booking.event_id
			LEFT JOIN event_user ON event.id = event_user.event_id AND access = ".EventModel::ACCESS_PERSONAL."
			LEFT JOIN event_folder ON event.id = event_folder.event_id AND access = ".EventModel::ACCESS_GROUP."
			LEFT JOIN folder ON event_folder.category_id = folder.id AND event_folder.category_id IS NOT NULL
			LEFT JOIN event_group ON event.id = event_group.event_id AND access = ".EventModel::ACCESS_CAMPUS."
			LEFT JOIN event_campus ON event.id = event_campus.event_id AND access = ".EventModel::ACCESS_CAMPUS."
			LEFT JOIN event_attendance ON event.id = event_attendance.event_id
			WHERE
			    event.deleted_at is NULL AND
				resource_booking.id IS NULL
				$access_query
				$dateQuery
				$filter_query
			GROUP BY id
			ORDER BY
				event.start_datetime, event.end_datetime
		";

        $allEvents = $this->db->getAll($query);

        /* Standardised output
         */
        $stack = new Collection();

        if (is_array($allEvents) && count($allEvents)) {
            foreach ($allEvents as $currentEvent) {
                $event = new Event();

                $event->setAuthorName($currentEvent['firstname'] . ' ' . $currentEvent['lastname']);
                $event->setAuthorId($currentEvent['uid']);
                $event->setEventId($currentEvent['id']);
                $event->setStartTime(
                    new Alaress_Date_Format($currentEvent['start_datetime'],'Y-m-d H:i:s')
                );
                $event->setEndTime(
                    new Alaress_Date_Format($currentEvent['end_datetime'],'Y-m-d H:i:s' )
                );
                $event->setType($currentEvent['type']);
                $event->setTitle($currentEvent['title']);
                $event->setAccess($currentEvent['access']);
                $event->setEventType($currentEvent['eventType']);
                $event->setColour($currentEvent['eventColour']);
                $event->setDetail($currentEvent['detail']);
                $event->setLocation($currentEvent['location']);
                $event->setPublishDate($currentEvent['publish_date']);
                $event->setAllDay(!empty($currentEvent['allday']));
                $event->setSeriesId($currentEvent['series_id']);

                /* If you have write access to the event
                 */
                if ($this->getPerm($currentEvent) > 1) {
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
                $event->setCompleted((bool)$currentEvent['completed']);

                // TODO port this into SQL query so we don't bother building the event just to throw it away
                $isFutureEvent   = $event->getPublishDate()->getUnixTimeStamp() >= time();
                $isAlwaysVisible = $viewer->isSuperuser() || $event->isOwner() || $event->isEditable();

                if($isAlwaysVisible || !$isFutureEvent) {
                    $stack->add($event);
                }
            }
        }

        return $stack;
    }

    function getDayByGroup($currentDay, $groupList, $campusList)
    {

        $query = "SELECT DISTINCT id, owner,
							TIME_FORMAT(start_datetime, '" . Config::get('calendar_time_format') . "') as startTime,
							TIME_FORMAT(end_datetime, '" . Config::get('calendar_time_format') . "') as endTime,
							type, title, location, detail, access, event_campus.campus_id as sid
						FROM event
						LEFT JOIN event_campus ON event.id = event_campus.event_id
						LEFT JOIN event_group ON event.id = event_group.event_id
						WHERE start_datetime = '" . date("Y-m-d", $currentDay) . "'
						AND event.deleted_at IS NULL
							AND (
								  (access = '3' AND event_campus.campus_id IN(" . implode(',', $campusList)
            . ") AND event_group.group_id IN(" . implode(',', $groupList) . ") )
								)
						GROUP BY id
						ORDER BY event.start_datetime, event.end_datetime";

        return $this->db->getAll($query);
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
        $output = array();
        $query  = "SELECT group_id FROM event_group WHERE event_id = ".intval($eventID);
        $result = $this->db->query($query);
        while ($event = $this->db->getNextArray($result)) {
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
        $output = array();
        $query  = "SELECT campus_id FROM event_campus WHERE event_id = ".intval($eventID);
        $result = $this->db->query($query);
        while ($event = $this->db->getNextArray($result)) {
            $output[] = $event['campus_id'];
        }
        return $output;
    }

    /**
     * Gets all event categories for the given event ID.
     *
     * @param int $eventID
     *
     * @return array
     */
    public function getEventCategory($eventID)
    {
        $output = array();
        $query  = "SELECT category_id FROM event_folder WHERE event_id = ".intval($eventID);
        $result = $this->db->query($query);
        while ($event = $this->db->getNextArray($result)) {
            $output[] = $event['category_id'];
        }
        return $output;
    }

    function getMonthList()
    {
        for ($i = 1; $i <= 12; $i++) {
            $output['DROP_MONTH_NUMBER'] = $i;
            $output['DROP_MONTH_NAME']   = date('F', mktime(0, 0, 0, $i, 1));
            $monthList[]                 = $output;
        }
        return $monthList;
    }

    function getYearList()
    {
        for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++) {
            $output['DROP_YEAR_NUMBER'] = $i;
            $yearList[]                 = $output;
        }
        return $yearList;
    }

    function createGroups($groupList, $eventID)
    {
        if (is_array($groupList)) {
            foreach ($groupList as $groupID) {
                $eventGroup = array(
                    'event_id' => $eventID,
                    'group_id' => $groupID,
                );
                $this->db->insert('event_group', $eventGroup);
            }
        }
    }

    function createCampus($campusList, $eventID)
    {
        if (is_array($campusList)) {
            foreach ($campusList as $campusID) {
                $eventCampus = array(
                    'event_id'  => $eventID,
                    'campus_id' => $campusID,
                );
                $this->db->insert('event_campus', $eventCampus);
            }
        }
    }

    function delPerms($eventID)
    {

        $this->db->delete('event_group', 'event_id', $eventID);
        $this->db->delete('event_campus', 'event_id', $eventID);
    }

    function updateFilters($filters, $view)
    {
        // set the filters into the session
        foreach ($filters as $filter) {
            $_SESSION['calendarFilter'][$filter[0]] = preg_replace('/^[^0-9]+/', '', $filter[1]);
        }

        // Update database record only if there are changes
        if ($this->db->update('user', 'id', array('id' => $_SESSION['id'], 'calendar_view' => $view))) {
            return true;
        }

        return false;
    }

    function create($event)
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

    function update($event)
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

        $groupList  = $event['groupList'];
        $campusList = $event['campusList'];

        unset($event['groupList']);
        unset($event['campusList']);

        /* Clear attendance if it has changed from true to false */
        if ($event['attendance'] < $cleanEvent['attendance']) {
            $this->removeAttendanceByEvent($event['id']);
        }

        parent::update($event);

        $this->delPerms($event['id']);

        $this->createGroups($groupList, $event['id']);
        $this->createCampus($campusList, $event['id']);

        return $event['id'];
    }

    function delete($eventID)
    {
        $event = $this->getByID($eventID);

        if ($this->getPerm($eventID) < UserAccess::ACCESS_WRITE) {
            error(34);
        }

        $this->removeAttendanceByEvent($eventID);

        parent::delete($eventID);
        $this->delPerms($eventID);
    }
}
