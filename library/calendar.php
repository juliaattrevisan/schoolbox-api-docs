<?php

class Calendar extends DeprecatedModel {

    const ATTENDANCE_STATUS_PENDING = 1;

    const ATTENDANCE_STATUS_APPROVED = 2;

    const ATTENDANCE_STATUS_REJECTED = 3;

    const ATTENDANCE_INTENTION_PENDING = 1;

    const ATTENDANCE_INTENTION_PRESENT = 2;

    const ATTENDANCE_INTENTION_ABSENT = 3;

    function Calendar() {

        $this->_table = "event";
        $this->_index = "id";

        $this->groupHandler = new Group(CALENDAR_SERVICE);

        parent::DeprecatedModel();
    }

    /**
     * Get a list of upcoming events with a summary tally of attendances
     * for both Pending and Approved statuses
     *
     * @return array|bool
     */
    public function getUpcomingEventAttendance() {
        $query = "
			SELECT
				event.id,
				event.note,
				event.startDate,
				event.startTime,
				event.endDate,
				event.endTime,
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
			AND publish_date < NOW()
			GROUP BY event.id
			ORDER BY CONCAT(event.startDate, ' ', event.startTime) DESC
		";

        return $this->db->getAll($query);
    }

    /**
     * Get a list of people who are attending/pending for a particular
     * event attendance
     *
     * @param $eventId
     * @param $status
     * @return mixed
     */
    private function getByStatus($eventId, $status) {
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
     * @return mixed
     */
    public function getPending($eventId) {
        return $this->getByStatus($eventId, self::ATTENDANCE_STATUS_PENDING);
    }

    /**
     * Get all the people approved for an event
     *
     * @param $eventId
     * @return mixed
     */
    public function getAttending($eventId) {
        return $this->getByStatus($eventId, self::ATTENDANCE_STATUS_APPROVED);
    }

    /**
     * Get all the people approved for an event
     *
     * @param $eventId
     * @return mixed
     */
    public function getRejected($eventId) {
        return $this->getByStatus($eventId, self::ATTENDANCE_STATUS_REJECTED);
    }

    public function getAttendeeList($eventId) {
        $event = $this->getByID($eventId);

        $query = "
            SELECT u.*,
                ea.id, ea.status, ea.intention
            FROM event_attendance ea
            LEFT JOIN user u ON u.id = ea.user_id
            WHERE ea.event_id = {$eventId}
        ";

        return $this->db->getAll($query);
    }

    public function getAttendance($attendanceId) {
        $query = "
            SELECT *
            FROM event_attendance
            WHERE id = {$attendanceId}
            LIMIT 1
        ";

        return $this->db->getRow($query);
    }

    public function getAttendanceByEventAndUser($eventId, $userId) {
        $query = "
            SELECT *
            FROM event_attendance
            WHERE event_id = {$eventId}
              AND user_id = {$userId}
            LIMIT 1
        ";

        return $this->db->getRow($query);
    }

    public function createAttendance($attendance) {
        return $this->db->insert('event_attendance', $attendance);
    }

    public function updateAttendance($attendance) {
        return $this->db->update('event_attendance', 'id', $attendance);
    }

    public function removeAttendance($attendanceId) {
        return $this->db->delete('event_attendance', 'id', $attendanceId);
    }

    public function removeAttendanceByEvent($eventId) {
        return $this->db->delete('event_attendance', 'event_id', $eventId);
    }

    function displayColour($id) {
        if (!is_array($this->colourSet)) {
            //build cache of colours on first call
            $query = "SELECT eventTypeID, eventColour FROM luEventType";
            $result = $this->db->query($query);
            while ($row = $this->db->getNextArray($result)) {
                $this->colourSet[$row['eventTypeID']] = '#' . $row['eventColour'];
            }
        }
        return $this->colourSet[$id];

    }

    function getPerm($eventID) {

        if (is_array($eventID)) {
            $event =& $eventID;
        } else {
            $event = $this->getByID($eventID);
        }

        if ($_SESSION['su']) {
            return 3;
        }

        switch ($event['access']) {

            case 1: // personal
                return $event['owner'] == $_SESSION['id'] ? 2 : 0;

            case 2: // single group
                $this->groupHandler->setService(HOMEPAGE_SERVICE);
                $permission = $this->groupHandler->getPermission($event['sid']);
                $this->groupHandler->setService(CALENDAR_SERVICE);
                return $permission;

            case 3: // campus

                if (is_array($event['campusList'])) {
                    $campusList = $event['campusList'];
                } else {
                    $campusList = $this->getEventCampus($event['id']);
                }

                //all campuses are represented by 0. if the user has all let them in
                $campusList[] = 0;
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

    function getTypeList() {
        $query = "SELECT eventTypeID, eventType, eventColour FROM luEventType";
        return $this->db->getAll($query);
    }

    function getCampusList($type = 1) {
        $query = "SELECT id, name FROM luCampus";
        if (!$_SESSION['su']) {
            $query .= ", userAccess WHERE (luCampus.id = userAccess.sid OR userAccess.sid = 0) AND
				userAccess.service = '2' AND uid = " . $_SESSION['id'] . " AND type > $type";
        }
        $campusList = $this->db->getAll($query);
        if (is_array($campusList)) {
            foreach ($campusList as $campus) {
                $data['CAMPUS_NAME'] = $campus['name'];
                $data['CAMPUS_ID'] = $campus['id'];
                $output[] = $data;
            }
        }
        return $output;
    }

    /**
     * Used on the Calendar screens to filter events
     *
     * @param $userId
     *
     * @return array
     */
    public function getFilterCampusList($userId) {
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
                    'CAMPUS_ID' => $currentCampus['id'],
                    'CAMPUS_NAME' => $currentCampus['name'],
                );
            }
        } else {
            $output = array();
        }

        return $output;
    }


    function getGroupList() {
        $query = "SELECT * FROM groups";
        $groupList = $this->db->getAll($query);
        if (is_array($groupList)) {
            foreach ($groupList as $group) {
                $data['GROUP_ID'] = $group['id'];
                $data['GROUP_NAME'] = $group['name'];
                $output[] = $data;
            }
        }
        return $output;
    }

    /**
     * Get an event by its id
     *
     * @param $eventID
     * @return array|bool
     */
    public function getByID($eventID) {
        $query = "
			SELECT
				e.id, e.series_id, e.owner, e.access, e.sid, e.type, e.note, e.startDate, e.endDate,
				e.location, e.attendance, e.detail, e.publish_date, e.allday,
				DATE_FORMAT(e.startTime, '" . Cfg::get('calendar_time_format') . "') startTime,
				DATE_FORMAT(e.endTime, '" . Cfg::get('calendar_time_format') . "') endTime,
				u.title, u.firstname, u.lastname
			FROM {$this->_table} e
			LEFT JOIN user u ON u.id = e.owner
			WHERE e.{$this->_index} = $eventID
		";

        return $this->db->getRow($query);
    }

    function getAllInCategory($categoryID, $config = false) {

        //event limit changed from posts to days
        if ($config['days'] != 0 && $config['days'] != '') {
            $where = " AND startDate < ADDDATE(CURDATE(), INTERVAL {$config['days']} DAY) ";
        } else {
            $where = "";
        }

        $query = "SELECT event.id, DATE_FORMAT(startDate, '%a %D %b') as outputDate,
						startDate,
						DATE_FORMAT(startDate, '%Y') as year, DATE_FORMAT(startDate, '%c') as month, DATE_FORMAT(startDate, '%e') as day,
						TIME_FORMAT(startTime, '" . Cfg::get('calendar_time_format') . "') as startTimeString,
						TIME_FORMAT(endTime, '" . Cfg::get('calendar_time_format') . "') as endTimeString,
						type, note, location, detail,
						user.id as uid, title, firstname, lastname,
						eventType, eventColour,
						attendance
				  FROM event
				  LEFT JOIN user ON owner = user.id
				  LEFT JOIN luEventType ON type = eventTypeID
				  WHERE access = '2' AND sid = '" . $categoryID . "' AND( startDate >= CURRENT_DATE() $where) ORDER BY startDate, startTime";
		if (!empty($config['size'])) {
			$query .= ' LIMIT '.$config['size'];
		}
        return $this->db->getAll($query);
    }

    function getAllInSeriesByEventID($eventID) {
        $query = "SELECT * FROM event WHERE series_id = (
                    SELECT series_id FROM event WHERE id = '$eventID'
                  )";
        return $this->db->getAll($query);
    }

    function getSeriesID($eventID) {
        $query = "SELECT `series_id` FROM `event` WHERE `id` = '$eventID'";
        return $this->db->getOne($query);
    }

    function isInSeries($eventID) {
        return $this->getSeriesID($eventID) != null;
    }

    function getAllInSeries($seriesID) {
        $query = "SELECT *
                  FROM `event`
                  WHERE `series_id` = '$seriesID'
                  ORDER BY `startDate` ASC";
        return $this->db->getAll($query);
    }

    function getUpperSeriesIndex() {
        $query = "SELECT `series_id`
                  FROM `event`
                  ORDER BY series_id DESC";
        return $this->db->getOne($query);
    }

    function getSeriesRecurrence($seriesID) {
        $series = $this->getAllInSeries($seriesID);
        $first = 0;
        $next = 1;
        $last = count($series) - 1;

        $dateFirst = new Alaress_Date_Format($series[$first]['startDate'], 'y-m-d');
        $dateNext = new Alaress_Date_Format($series[$next]['startDate'], 'y-m-d');
        $dateLast = new Alaress_Date_Format($series[$last]['startDate'], 'y-m-d');

        $diff = (int)(($dateNext->getUnixTimestamp() - $dateFirst->getUnixTimestamp()) / 86400);

        $recurMap = array(
            1 => 'daily',
            7 => 'weekly',
            14 => 'fortnightly',
            28 => 'monthly',
            29 => 'monthly',
            30 => 'monthly',
            31 => 'monthly',
        );

        if (isset($recurMap[$diff])) {
            return array(
                'freq' => $recurMap[$diff],
                'start' => $dateFirst,
                'end' => $dateLast
            );
        }

        return array(
            'freq' => 'periodically',
            'start' => $dateFirst,
            'end' => $dateLast
        );
    }

	/**
	 * Get all the submission events for the specified day
	 *
	 * @param $startTimestamp
	 * @param $endTimestamp
	 *
	 * @return array|bool
	 */
    public function getSubmissionEvents($startTimestamp, $endTimestamp) {

        //get a list of all the possible submission box categories
        $this->groupHandler->setService(HOMEPAGE_SERVICE);
        $catList = $this->groupHandler->involvedInCategoryList($_SESSION['id']);
        $this->groupHandler->setService(CALENDAR_SERVICE);

        if (is_array($catList)) {
            foreach ($catList as $cat) {
                $catQuery[] = $cat['id'];
            }

            $query = "
				SELECT
					DISTINCT category.name AS category, category.id as cid, GROUP_CONCAT(co.code) AS synergyCode, submission_box.name, submission_box.description,
					submission_box.owner, submission_box.id, user.firstname, user.lastname, user.title,
					DATE_FORMAT(submission_box.date, '%w') as dayIndex,
					DATE_FORMAT(submission_box.date, '" . Cfg::get('calendar_time_format') . "') as dueTime,
					submission_box.accept_submission, submission_box.open_date, submission_box.date, submission_box.late_date, submission_box.created
				FROM (category, submission_box )
				LEFT JOIN categoryCode co ON co.categoryID = category.id
				LEFT JOIN user ON user.id = submission_box.owner ";

            /* If you aren't part of any categories, don't try to
             * do a whereIn
             */
            if (count($catQuery)) {
                $query .= "
					WHERE category.id = submission_box.category
					AND submission_box.category IN (" . implode(',', $catQuery) . ")
					AND submission_box.deleted = 0
					AND	DATE(submission_box.date) >= DATE(FROM_UNIXTIME('{$startTimestamp}'))
					AND DATE(submission_box.date) < DATE(FROM_UNIXTIME('{$endTimestamp}'))
					GROUP BY submission_box.id, category.id
					ORDER BY submission_box.date
				";
            }
			else {
                $query .= "
					WHERE DATE(submission_box.date) >= DATE(FROM_UNIXTIME('{$startTimestamp}'))
					AND DATE(submission_box.date) < DATE(FROM_UNIXTIME('{$endTimestamp}'))
					AND submission_box.deleted = 0
					GROUP BY submission_box.id
					ORDER BY submission_box.date
				";
            }

            $submissions = $this->db->getAll($query);
        }

        /* Standardised output
         */
        $stack = new Calendar_Event_Collection();

        if (is_array($submissions) && count($submissions)) {
            foreach ($submissions as $submission) {
                $event = new Calendar_Event_Type_Submission();

                $event->setCategoryId($submission['cid']);
                $event->setCategoryName($submission['category']);
                $event->setCategoryCodes($submission['synergyCode']);
                $event->setAuthorId($submission['owner']);
                $event->setAuthorName($submission['firstname'] . ' ' . $submission['lastname']);
                $event->setSubmissionBoxId($submission['id']);
                $event->setSubmissionBoxName($submission['name']);
                $event->setSubmissionBoxDetail($submission['description']);
                $event->setColour($this->displayColour(4));
	            $event->setStartTime(new Alaress_Date_Format($submission['date'], 'y-m-d h:i:s'));
		        $event->setAllDay(stristr($submission['date'], '23:59:59'));

                $stack->add($event);
            }
        }

        return $stack;
    }

    function search($keyword, $offset = 0, $limit = 20, &$count = null, $sort_by = 'score', $sort_dir = 'desc') {
        $keyword = mysql_real_escape_string($keyword);

        if (is_array($_SESSION['campus'])) {
            $campusList = $_SESSION['campus'];

            $campusQuery = "eventCampus.campusID IN(" . implode(',', $campusList) . ")";
        } else {
            $campusList = array(1);

            $campusQuery = '1 = 1';
        }

        // get accessible groups
        $groupList = array();
        $groupQuery = '';

        $this->groupHandler->setService(HOMEPAGE_SERVICE);
        $tempList = $this->groupHandler->involvedInCategoryList($_SESSION['id']);

        if (is_array($tempList)) {

            foreach ($tempList as $temp) {
                $groupList[] = $temp['id'];
            }

            /* If there are no groups prevent sql error
             */
            if (count($groupList)) {
                $groupQuery = "OR (access = '2' AND sid IN (" . implode(',', $groupList) . "))";
            }
        }

        $this->groupHandler->setService(CALENDAR_SERVICE);

        //give people with userAccess to moderate Calendar Access to view all Camups Posts
        if (is_array($_SESSION['campus'])) {
            if ($this->groupHandler->getPermission($_SESSION['campus'][0]) > WRITE_ACCESS) {
                $groupChunk = "";
            } else {
                $groupChunk = "AND eventGroup.groupID = {$_SESSION['gid']}";
            }
        }

        $event_query = "SELECT DISTINCT event.id, owner,
                            GROUP_CONCAT(DISTINCT eventCampus.campusID) as campusList,
                            TIME_FORMAT(startTime, '" . Cfg::get('calendar_time_format') . "') as startTime,
                            TIME_FORMAT(endTime, '" . Cfg::get('calendar_time_format') . "') as endTime,
                            TIME_TO_SEC(startTime) as startTimestamp,
                            TIME_TO_SEC(endTime) as endTimestamp,
                            startDate, endDate,
                            type, note, access, sid, location, detail,
                            user.id as uid, title, firstname, lastname,
                            eventType, eventColour,
                            MATCH (`note`, `detail`, `location`) AGAINST ('$keyword') AS `score`
                        FROM event
                        LEFT JOIN user ON owner = user.id
                        LEFT JOIN luEventType ON type = eventTypeID
                        LEFT JOIN eventCampus ON event.id = eventCampus.eventID
                        LEFT JOIN eventGroup ON event.id = eventGroup.eventID
                        WHERE (    (access = '1' AND owner = '" . $_SESSION['id'] . "')
                                  $groupQuery
                                  OR (access = '3' AND $campusQuery $groupChunk OR owner = '" . $_SESSION['id'] . "')
                                )
                            AND MATCH (`note`, `detail`, `location`) AGAINST ('$keyword')
                        GROUP BY id
                        ORDER BY $sort_by $sort_dir
                        LIMIT $offset, $limit";


        $count_query = "SELECT COUNT(DISTINCT event.id) AS event_count
						FROM event
						LEFT JOIN user ON owner = user.id
						LEFT JOIN luEventType ON type = eventTypeID
						LEFT JOIN eventCampus ON event.id = eventCampus.eventID
						LEFT JOIN eventGroup ON event.id = eventGroup.eventID
						WHERE (
							(access = '1' AND owner = '" . $_SESSION['id'] . "')
							$groupQuery
							OR (access = '3' AND $campusQuery $groupChunk OR owner = '" . $_SESSION['id'] . "')
						)
						AND MATCH (`note`, `detail`, `location`) AGAINST ('$keyword')
						";

        $events = $this->db->getAll($event_query);
        $count = $this->db->getOne($count_query);

        return $events;
    }

	/**
	 * Get all events occurring on the specified date
	 *
	 * @param $startTimestamp
	 * @param $endTimestamp
	 * @return Calendar_Event_Collection
	 */
	public function getDayEvents($startTimestamp, $endTimestamp) {
        if (is_array($_SESSION['campus'])) {
            $campusList = $_SESSION['campus'];
            $campusQuery = "eventCampus.campusID IN(" . implode(',', $campusList) . ")";
        } else {
            $campusList = array(1);
            $campusQuery = '1 = 1';
        }

        /* get accessible groups
         */
        $groupList = array();
        $groupQuery = '';

        $this->groupHandler->setService(HOMEPAGE_SERVICE);
        $tempList = $this->groupHandler->involvedInCategoryList($_SESSION['id']);

        if (is_array($tempList)) {
            foreach ($tempList as $temp) {
                $groupList[] = $temp['id'];
            }

            /* If there are no groups prevent sql error
             */
            if (count($groupList)) {
                $groupQuery = "OR (access = '2' AND sid IN (" . implode(',', $groupList) . "))";
            }
        }

        $this->groupHandler->setService(CALENDAR_SERVICE);

        /* Give people with userAccess to moderate Calendar Access to view all Camups Posts
         */
        if (is_array($_SESSION['campus'])) {
            if ($this->groupHandler->getPermission($_SESSION['campus'][0]) > WRITE_ACCESS) {
                $groupChunk = "";
            } else {
                $groupChunk = "AND eventGroup.groupID = {$_SESSION['gid']}";
            }
        }

        //get single events for the day
        $query = "
			SELECT DISTINCT event.id, owner,
				GROUP_CONCAT(DISTINCT eventCampus.campusID) as campusList,
				startDate, startTime, endDate, endTime, allday,
				SUBSTR(event.startDate, 1, 4) AS year,
				type, note, access, sid, location, detail, attendance,
				user.id as uid, title, firstname, lastname,
				eventType, eventColour,
				event.publish_date,
				category.id AS categoryId, category.name AS categoryName,
				event.completed
			FROM event
			LEFT JOIN user ON owner = user.id
			LEFT JOIN luEventType ON type = eventTypeID
			LEFT JOIN eventCampus ON event.id = eventCampus.eventID
			LEFT JOIN eventGroup ON event.id = eventGroup.eventID
			LEFT JOIN category ON event.sid = category.id AND event.sid IS NOT NULL
			WHERE
				(
					(access = '1' AND owner = '{$_SESSION['id']}')
					$groupQuery
					OR (access = '3' AND $campusQuery $groupChunk OR owner = '{$_SESSION['id']}')
				)
				AND (

					/* Start display in the middle of multi-day events that have already begun at some point
					 * in the past
					 */
					((DATE(startDate) <= DATE(FROM_UNIXTIME('{$startTimestamp}'))) AND (DATE(endDate) < DATE(FROM_UNIXTIME('{$endTimestamp}'))))
					OR
					/* Start display for events that are still coming up within the specified time interval
					 */
					((DATE(startDate) >= DATE(FROM_UNIXTIME('{$startTimestamp}'))) AND (DATE(startDate) < DATE(FROM_UNIXTIME('{$endTimestamp}'))))
				)
				/* Don't include events that have already ended
				 */
				AND (
					endDate >= FROM_UNIXTIME('{$startTimestamp}')
				)
			GROUP BY id
			ORDER BY event.startTime, event.endTime
		";

        $allEvents = $this->db->getAll($query);

        /* Standardised output
         */
        $stack = new Calendar_Event_Collection();

        if (is_array($allEvents) && count($allEvents)) {
            foreach ($allEvents as $currentEvent) {
                $event = new Calendar_Event_Type_Day();

                $event->setAuthorName($currentEvent['firstname'] . ' ' . $currentEvent['lastname']);
                $event->setAuthorId($currentEvent['uid']);
                $event->setEventId($currentEvent['id']);
                $event->setStartTime(new Alaress_Date_Format($currentEvent['startDate'] . ' ' . $currentEvent['startTime'], 'y-m-d h:i:s'));
                $event->setEndTime(new Alaress_Date_Format($currentEvent['endDate'] . ' ' . $currentEvent['endTime'], 'y-m-d h:i:s'));
                $event->setType($currentEvent['type']);
                $event->setNote($currentEvent['note']);
                $event->setAccess($currentEvent['access']);
                $event->setEventType($currentEvent['eventType']);
                $event->setColour($currentEvent['eventColour']);
                $event->setCampusList($currentEvent['campusList']);
                $event->setDetail($currentEvent['detail']);
                $event->setLocation($currentEvent['location']);
                $event->setPublishDate($currentEvent['publish_date']);
                $event->setSid($currentEvent['sid']);
                $event->setAllDay(!empty($currentEvent['allday']));

				if ($this->getPerm($currentEvent) > 1) {
                    $event->setIsEditable(true);
                }
				else {
                    $event->setIsEditable(false);
                }

                if ($currentEvent['access'] == 2) {
                    $event->setCategoryId($currentEvent['categoryId']);
                    $event->setCategoryname($currentEvent['categoryName']);
                }
                $event->setAttendance($currentEvent['attendance']);
				$event->setCompleted($currentEvent['completed']);

                $stack->add($event);
            }
        }

        return $stack;
    }

    function getDayByGroup($currentDay, $groupList, $campusList) {

        $query = "SELECT DISTINCT id, owner,
							TIME_FORMAT(startTime, '" . Cfg::get('calendar_time_format') . "') as startTime,
							TIME_FORMAT(endTime, '" . Cfg::get('calendar_time_format') . "') as endTime,
							type, note, location, detail, access, eventCampus.campusID as sid
						FROM event
						LEFT JOIN eventCampus ON event.id = eventCampus.eventID
						LEFT JOIN eventGroup ON event.id = eventGroup.eventID
						WHERE startDate = '" . date("Y-m-d", $currentDay) . "'
							AND (
								  (access = '3' AND eventCampus.campusID IN(" . implode(
            ',', $campusList
        ) . ") AND eventGroup.groupID IN(" . implode(',', $groupList) . ") )
								)
						GROUP BY id
						ORDER BY event.startTime, event.endTime";

        return $this->db->getAll($query);
    }

    function getStudentBirthdays($currentDay) {

        require_once '../library/mssql.php';
        $db = new MSSQL;

//		$query = "SELECT  vStudents.StudentID, vStudents.StudentGiven1, vStudents.StudentSurname,
//						vStudents.StudentBirthDate, vStudents.StudentCampus, datediff(year,studentbirthdate,getdate()) as age,
//						vStudents.StudentFormDescription, convert(varchar,studentbirthdate,106)  as 'la'
//						FROM SynergyOne.dbo.vStudents vStudents
//						WHERE
//						vStudents.FileSemester=".$_SESSION['FileSemester']."
//						AND vStudents.FileYear=".$_SESSION[ 'FileYear']."
//						AND day(studentbirthdate) = ". date("j", $currentDay) ."
//						AND month(studentbirthdate) =". date("n", $currentDay) ."
//						AND StudentCampus = 'G'";

        $query = "SELECT  vStudents.StudentID, vStudents.StudentGiven1, vStudents.StudentSurname,
						vStudents.StudentBirthDate, vStudents.StudentCampus, datediff(year,studentbirthdate,getdate()) as age,
						vStudents.StudentFormDescription, convert(varchar,studentbirthdate,106)  as 'la'
						FROM SynergyOne.dbo.vStudents vStudents
						WHERE
						vStudents.FileSemester=" . $_SESSION['FileSemester'] . "
						AND vStudents.FileYear=" . $_SESSION['FileYear'] . "
						AND day(studentbirthdate) = " . date("j", $currentDay) . "
						AND month(studentbirthdate) =" . date("n", $currentDay);

        $birthdayList = $db->getAll($query);

        /* Standardised output
         */
        $stack = new Calendar_Event_Collection();

        if (is_array($birthdayList) && count($birthdayList)) {
            foreach ($birthdayList as $birthday) {
                $event = new Calendar_Event_Type_StudentBirthday();
                $event->setName($birthday['StudentGiven1'] . ' ' . $birthday['StudentSurname']);
                $event->setForm($birthday['StudentFormDescription']);
                $event->setAge($birthday['age']);
                $event->setDate($birthday['la']);
                $event->setStartTime(new Alaress_Date_Format(strtotime($birthday['StudentBirthdate']), 'U'));
                $event->setBirthdayNote();
                $stack->add($event);
            }
        }

        return $stack;
    }

    function getEventGroup($eventID) {
        $output = array();
        $query = "SELECT groupID FROM eventGroup WHERE eventID = $eventID";
        $result = $this->db->query($query);
        while ($event = $this->db->getNextArray($result)) {
            $output[] = $event['groupID'];
        }
        return $output;
    }

    function getEventCampus($eventID) {
        $output = array();
        $query = "SELECT campusID FROM eventCampus WHERE eventID = $eventID";
        $result = $this->db->query($query);
        while ($event = $this->db->getNextArray($result)) {
            $output[] = $event['campusID'];
        }
        return $output;
    }

    function getMonthList() {
        for ($i = 1; $i <= 12; $i++) {
            $output['DROP_MONTH_NUMBER'] = $i;
            $output['DROP_MONTH_NAME'] = date('F', mktime(0, 0, 0, $i, 1));
            $monthList[] = $output;
        }
        return $monthList;
    }

    function getYearList() {
        for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++) {
            $output['DROP_YEAR_NUMBER'] = $i;
            $yearList[] = $output;
        }
        return $yearList;
    }

    function createGroups($groupList, $eventID) {
        if (is_array($groupList)) {
            foreach ($groupList as $groupID) {
                $eventGroup = array(
                    'eventID' => $eventID,
                    'groupID' => $groupID,
                );
                $this->db->insert('eventGroup', $eventGroup);
            }
        }
    }

    function createCampus($campusList, $eventID) {
        if (is_array($campusList)) {
            foreach ($campusList as $campusID) {
                $eventCampus = array(
                    'eventID' => $eventID,
                    'campusID' => $campusID,
                );
                $this->db->insert('eventCampus', $eventCampus);
            }
        }
    }

    function delPerms($eventID) {

        $this->db->delete('eventGroup', 'eventID', $eventID);
        $this->db->delete('eventCampus', 'eventID', $eventID);
    }

    function updateFilters($filters, $view) {
        // set the filters into the session
        foreach($filters as $filter) {
            $_SESSION['calendarFilter'][$filter[0]] = preg_replace('/[^0-9]/', '', $filter[1]);
        }

        // update the user table to hold onto the view that they last used
        if($this->db->update('user', 'id', array('id' => $_SESSION['id'], 'calendar_view' => $view))) {
            // update the session to cache the db change
            $_SESSION['calendar_view'] = $view;
            return true;
        }
        return false;
    }

    function create($event) {

        if ($this->getPerm($event) < WRITE_ACCESS) {
            error(35);
        }

        $groupList = $event['groupList'];
        $campusList = $event['campusList'];

        unset($event['groupList']);
        unset($event['campusList']);

        $eventID = parent::create($event);

        $this->createGroups($groupList, $eventID);
        $this->createCampus($campusList, $eventID);

        return $eventID;
    }

    function update($event) {

        $cleanEvent = $this->getByID($event['id']);


        /* Check perms on existing event */
        if ($this->getPerm($event['id']) < WRITE_ACCESS) {
            error(34);
        }

        /* Check perms on what we want the event to become */
        if ($this->getPerm($event) < WRITE_ACCESS) {
            error(34);
        }

        $groupList = $event['groupList'];
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

    function delete($eventID) {
        $event = $this->getByID($eventID);

        if ($this->getPerm($eventID) < WRITE_ACCESS) {
            error(34);
        }

        $this->removeAttendanceByEvent($eventID);

        parent::delete($eventID);
        $this->delPerms($eventID);
    }

    /**
     * Removes content
     *
     * TODO: Refactor
     *
     * @param  $cid
     * @return void
     */
    public function homepageClean($cid) {
        $this->db->delete($this->_table, 'sid', $cid);
    }
}