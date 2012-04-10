<?php

class Calendar extends DeprecatedModel {

    const ATTENDANCE_STATUS_PENDING = 1;

    const ATTENDANCE_STATUS_APPROVED = 2;

    const ATTENDANCE_STATUS_REJECTED = 3;

    const ATTENDANCE_INTENTION_PENDING = 1;

    const ATTENDANCE_INTENTION_PRESENT = 2;

    const ATTENDANCE_INTENTION_ABSENT = 3;

	function Calendar () {

		$this->_table = "event";
		$this->_index = "id";

		$this->groupHandler = new Group(CALENDAR_SERVICE);

		parent::DeprecatedModel();
	}

    public function getAttendeeList ($eventId) {
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

    public function getAttendance ($attendanceId) {
        $query = "
            SELECT *
            FROM event_attendance
            WHERE id = {$attendanceId}
            LIMIT 1
        ";

        return $this->db->getRow($query);
    }

    public function getAttendanceByEventAndUser ($eventId, $userId) {
        $query = "
            SELECT *
            FROM event_attendance
            WHERE event_id = {$eventId}
              AND user_id = {$userId}
            LIMIT 1
        ";

        return $this->db->getRow($query);
    }

    public function createAttendance ($attendance) {
        return $this->db->insert('event_attendance', $attendance);
    }

    public function updateAttendance ($attendance) {
        return $this->db->update('event_attendance', 'id', $attendance);
    }

    public function removeAttendance ($attendanceId) {
        return $this->db->delete('event_attendance', 'id', $attendanceId);
    }

    public function removeAttendanceByEvent ($eventId) {
        return $this->db->delete('event_attendance', 'event_id', $eventId);
    }

	function displayColour($id) {
		if(!is_array($this->colourSet) ) {
			//build cache of colours on first call
			$query = "SELECT eventTypeID, eventColour FROM luEventType";
			$result = $this->db->query($query);
			while($row = $this->db->getNextArray($result) ) {
				$this->colourSet[$row['eventTypeID']] = '#'.$row['eventColour'];
			}
		}
		return $this->colourSet[$id];

	}

	function getPerm($eventID) {

		if (is_array($eventID))
			$event =& $eventID;
		else
			$event = $this->getByID($eventID);

		if ($_SESSION['su'])
			return 3;

		switch ($event['access']) {

			case 1: // personal
				return $event['owner'] == $_SESSION['id'] ? 2 : 0;

			case 2: // single group
				$this->groupHandler->setService(HOMEPAGE_SERVICE);
				$permission = $this->groupHandler->getPermission($event['sid']);
				$this->groupHandler->setService(CALENDAR_SERVICE);
				return $permission;

			case 3: // campus

				if (is_array($event['campusList']))
					$campusList = $event['campusList'];
				else
					$campusList = $this->getEventCampus($event['id']);

				//all campuses are represented by 0. if the user has all let them in
				$campusList[] = 0;
				if(is_array($campusList)) {
					foreach($campusList as $campus) {
						$permission = $this->groupHandler->getPermission($campus);
						if($output < $permission || !$output)
							$output = $permission;
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
			$query .=", userAccess WHERE (luCampus.id = userAccess.sid OR userAccess.sid = 0) AND
				userAccess.service = '2' AND uid = ".$_SESSION['id']." AND type > $type";
		}
		$campusList = $this->db->getAll($query);
		if (is_array($campusList)) {
			foreach ($campusList as $campus) {
				$data['CAMPUS_NAME'] = $campus['name'];
				$data['CAMPUS_ID'] 	= $campus['id'];
				$output[] = $data;
			}
		}
		return $output;
	}



	function getGroupList() {
		$query = "SELECT * FROM groups";
		$groupList = $this->db->getAll($query);
		if (is_array($groupList)) {
			foreach ($groupList as $group) {
				$data['GROUP_ID'] 	= $group['id'];
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
				e.id, e.owner, e.access, e.sid, e.type, e.note, e.date, e.location, e.attendance, e.detail, e.publish_date,
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
		if($config['size'] != 0 && $config['size'] != '')
				$where = " AND date < ADDDATE(CURDATE(), INTERVAL {$config['size']} DAY) ";
		else
				$where = "";

		$query = "SELECT event.id, DATE_FORMAT(date, '%a %D %b') as outputDate,
						date,
						DATE_FORMAT(date, '%Y') as year, DATE_FORMAT(date, '%c') as month, DATE_FORMAT(date, '%e') as day,
						TIME_FORMAT(startTime, '".Cfg::get('calendar_time_format')."') as startTimeString,
						TIME_FORMAT(endTime, '".Cfg::get('calendar_time_format')."') as endTimeString,
						type, note, location, detail,
						user.id as uid, title, firstname, lastname,
						eventType, eventColour
				  FROM event
				  LEFT JOIN user ON owner = user.id
				  LEFT JOIN luEventType ON type = eventTypeID
				  WHERE access = '2' AND sid = '".$categoryID."' AND( date >= CURRENT_DATE() $where) ORDER BY date, startTime";

		return $this->db->getAll($query);
	}

	/**
	 * Get all the submission events for the specified day
	 *
	 * @param  $currentDay
	 * @return array|bool
	 */
	public function getSubmissionEvents($currentDay) {

		//get a list of all the possible submission box categories
		$this->groupHandler->setService(HOMEPAGE_SERVICE);
		$catList = $this->groupHandler->involvedInCategoryList($_SESSION['id']);
		$this->groupHandler->setService(CALENDAR_SERVICE);

		if (is_array($catList)) {
			foreach($catList as $cat) {
				$catQuery[] = $cat['id'];
			}

			$query = "SELECT DISTINCT category.name AS category, GROUP_CONCAT(co.code) AS synergyCode, submission_box.name, submission_box.description,
                        submission_box.owner, submission_box.id, user.firstname, user.lastname, user.title,
                        DATE_FORMAT(submission_box.date, '%w') as dayIndex,
					    DATE_FORMAT(submission_box.date, '" . Cfg::get('calendar_time_format') . "') as dueTime,
                        submission_box.accept_submission, submission_box.open_date, submission_box.date, submission_box.late_date, submission_box.created, student_submission.submitted, submission_return.returned
                      FROM (category, submission_box )
                      LEFT JOIN submission_return ON submission_box.id = submission_return.box_id AND submission_return.owner = {$_SESSION['id']}
                      LEFT JOIN student_submission ON submission_box.id = student_submission.box_id AND student_submission.owner = {$_SESSION['id']}
                      LEFT JOIN `file` f ON f.id = submission_box.fid
                      LEFT JOIN categoryCode co ON co.categoryID = category.id
                      LEFT JOIN user ON user.id = submission_box.owner ";

			/* If you aren't part of any categories, don't try to
			 * do a whereIn
			 */
			if (count($catQuery)) {
            	$query .= "WHERE category.id = submission_box.category
							AND submission_box.category IN (".implode(',', $catQuery).")
							AND submission_box.deleted = 0
							AND	DATE(submission_box.date) = DATE(FROM_UNIXTIME('$currentDay'))
							GROUP BY submission_box.id, category.id
							ORDER BY submission_box.date";
			}
			else {
				$query .= "WHERE DATE(submission_box.date) = DATE(FROM_UNIXTIME('$currentDay'))
							GROUP BY submission_box.id
							ORDER BY submission_box.date";
			}

			$submissions = $this->db->getAll($query);

            if (!empty($submissions)) {
                /* Convert date fields and set status */
                $date_fields = array('open_date', 'date', 'created', 'late_date', 'submitted', 'returned');

                foreach ($submissions as &$submission) {

                    foreach ($date_fields as $field_name) {
                        if (!empty($submission[$field_name])) {
                            $submission[$field_name] = new DateTime($submission[$field_name]);
                        }
                    }

                    if (!empty($submission['returned'])) {
                        $submission['status'] = 3;
                        $submission['relevant_date'] = $submission['returned'];
                    } elseif (!empty($submission['submitted'])) {
                        $submission['status'] = 2;
                        $submission['relevant_date'] = $submission['submitted'];
                    } else {
                        $submission['status'] = 1;
                        $submission['relevant_date'] = $submission['date'];
                    }

                }
            }

            return $submissions;
		} else {
            return false;
        }
	}

    function search ($keyword, $offset = 0, $limit = 20, &$count = null, $sort_by = 'score', $sort_dir = 'desc') {
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
            	$groupQuery = "OR (access = '2' AND sid IN (".implode(',', $groupList)."))";
			}
        }

        $this->groupHandler->setService(CALENDAR_SERVICE);

        //give people with userAccess to moderate Calendar Access to view all Camups Posts
        if(is_array($_SESSION['campus'])) {
            if($this->groupHandler->getPermission($_SESSION['campus'][0]) > WRITE_ACCESS)
                $groupChunk = "";
            else {
                $groupChunk = "AND eventGroup.groupID = {$_SESSION['gid']}";
            }
        }

        $event_query = "SELECT DISTINCT event.id, owner,
                            GROUP_CONCAT(DISTINCT eventCampus.campusID) as campusList,
                            TIME_FORMAT(startTime, '" . Cfg::get('calendar_time_format') . "') as startTime,
                            TIME_FORMAT(endTime, '" . Cfg::get('calendar_time_format') . "') as endTime,
                            TIME_TO_SEC(startTime) as startTimestamp,
                            TIME_TO_SEC(endTime) as endTimestamp,
                            date, type, note, access, sid, location, detail,
                            user.id as uid, title, firstname, lastname,
                            eventType, eventColour,
                            MATCH (`note`, `detail`, `location`) AGAINST ('$keyword') AS `score`
                        FROM event
                        LEFT JOIN user ON owner = user.id
                        LEFT JOIN luEventType ON type = eventTypeID
                        LEFT JOIN eventCampus ON event.id = eventCampus.eventID
                        LEFT JOIN eventGroup ON event.id = eventGroup.eventID
                        WHERE date >= NOW()
                            AND (    (access = '1' AND owner = '".$_SESSION['id']."')
                                  $groupQuery
                                  OR (access = '3' AND $campusQuery $groupChunk OR owner = '".$_SESSION['id']."')
                                )
                            AND MATCH (`note`, `detail`, `location`) AGAINST ('$keyword')
                        GROUP BY id
                        ORDER BY $sort_by $sort_dir
                        LIMIT $offset, $limit";

        $count_query = "SELECT COUNT(*)
                        FROM event
                        LEFT JOIN user ON owner = user.id
                        LEFT JOIN luEventType ON type = eventTypeID
                        LEFT JOIN eventCampus ON event.id = eventCampus.eventID
                        LEFT JOIN eventGroup ON event.id = eventGroup.eventID
                        WHERE date >= NOW()
                            AND (    (access = '1' AND owner = '".$_SESSION['id']."')
                                  $groupQuery
                                  OR (access = '3' AND $campusQuery $groupChunk OR owner = '".$_SESSION['id']."')
                                )
                            AND MATCH (`note`, `detail`, `location`) AGAINST ('$keyword')";

        $events = $this->db->getAll($event_query);
        $count = $this->db->getOne($count_query);

        return $events;
    }

	/**
	 * Get all events occurring on the specified date
	 *
	 * @param $currentDay
	 * @return array|bool
	 */
	public function getDayEvents($currentDay) {
		if (is_array($_SESSION['campus'])) {
			$campusList = $_SESSION['campus'];
			$campusQuery = "eventCampus.campusID IN(" . implode(',', $campusList) . ")";
		}
		else {
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
				$groupQuery = "OR (access = '2' AND sid IN (".implode(',', $groupList)."))";
			}
		}

		$this->groupHandler->setService(CALENDAR_SERVICE);

		/* Give people with userAccess to moderate Calendar Access to view all Camups Posts
		 */
		if (is_array($_SESSION['campus'])) {
			if ($this->groupHandler->getPermission($_SESSION['campus'][0]) > WRITE_ACCESS) {
				$groupChunk = "";
			}
			else {
				$groupChunk = "AND eventGroup.groupID = {$_SESSION['gid']}";
			}
		}

		//get single events for the day
		$query = "
			SELECT DISTINCT event.id, owner,
				GROUP_CONCAT(DISTINCT eventCampus.campusID) as campusList,
				TIME_FORMAT(startTime, '" . Cfg::get('calendar_time_format') . "') as startTime,
				TIME_FORMAT(endTime, '" . Cfg::get('calendar_time_format') . "') as endTime,
				TIME_TO_SEC(startTime) as startTimestamp,
				TIME_TO_SEC(endTime) as endTimestamp,
				type, note, access, sid, location, detail, attendance,
				user.id as uid, title, firstname, lastname,
				eventType, eventColour,
				event.publish_date,
				category.id AS categoryId, category.name AS categoryName
			FROM event
			LEFT JOIN user ON owner = user.id
			LEFT JOIN luEventType ON type = eventTypeID
			LEFT JOIN eventCampus ON event.id = eventCampus.eventID
			LEFT JOIN eventGroup ON event.id = eventGroup.eventID
			LEFT JOIN category ON event.sid = category.id AND event.sid IS NOT NULL
			WHERE date = '" . date("Y-m-d", $currentDay) . "'
				AND (    (access = '1' AND owner = '".$_SESSION['id']."')
					  $groupQuery
					  OR (access = '3' AND $campusQuery $groupChunk OR owner = '".$_SESSION['id']."')
					)
			GROUP BY id
			ORDER BY event.startTime, event.endTime
		";

		return $this->db->getAll($query);
	}

	function getDayByGroup($currentDay, $groupList, $campusList) {

		$query = "SELECT DISTINCT id, owner,
							TIME_FORMAT(startTime, '" . Cfg::get('calendar_time_format') . "') as startTime,
							TIME_FORMAT(endTime, '" . Cfg::get('calendar_time_format') . "') as endTime,
							type, note, location, detail, access, eventCampus.campusID as sid
						FROM event
						LEFT JOIN eventCampus ON event.id = eventCampus.eventID
						LEFT JOIN eventGroup ON event.id = eventGroup.eventID
						WHERE date = '" . date("Y-m-d", $currentDay) . "'
							AND (
								  (access = '3' AND eventCampus.campusID IN(" . implode(',', $campusList) . ") AND eventGroup.groupID IN(" . implode(',', $groupList) . ") )
								)
						GROUP BY id
						ORDER BY event.startTime, event.endTime";

		return $this->db->getAll($query);
	}

	function getStudentBirthdays($currentDay) {

		require_once '../library/mssql.php';
		$db = new MSSQL;

		$query = "SELECT  vStudents.StudentID, vStudents.StudentGiven1, vStudents.StudentSurname,
						vStudents.StudentBirthDate, vStudents.StudentCampus, datediff(year,studentbirthdate,getdate()) as age,
						vStudents.StudentFormDescription, convert(varchar,studentbirthdate,106)  as 'la'
						FROM SynergyOne.dbo.vStudents vStudents
						WHERE
						vStudents.FileSemester=".$_SESSION['FileSemester']."
						AND vStudents.FileYear=".$_SESSION[ 'FileYear']."
						AND day(studentbirthdate) = ". date("j", $currentDay) ."
						AND month(studentbirthdate) =". date("n", $currentDay) ."
						AND StudentCampus = 'G'";

		$birthdayList = $db->getAll($query);
		if (is_array($birthdayList)) {
			$i = 0;
			foreach ($birthdayList as $birthday) {
				$output[$i]['name'] = $birthday['StudentGiven1']." ".$birthday['StudentSurname'];
				$output[$i]['form'] = $birthday['StudentFormDescription'];
				$output[$i]['age'] 	= $birthday['age'];
				$output[$i]['birthdate'] = $birthday['la'];
				$i++;
			}
		}

		return $output;
	}

	function getEventGroup($eventID) {
		$output = array();
		$query = "SELECT groupID FROM eventGroup WHERE eventID = $eventID";
		$result = $this->db->query($query);
		while($event = $this->db->getNextArray($result)) {
			$output[] = $event['groupID'];
		}
		return $output;
	}

	function getEventCampus($eventID) {
		$output = array();
		$query = "SELECT campusID FROM eventCampus WHERE eventID = $eventID";
		$result = $this->db->query($query);
		while($event = $this->db->getNextArray($result)) {
			$output[] = $event['campusID'];
		}
		return $output;
	}

	function getMonthList() {
		for($i=1; $i <= 12; $i++) {
			$output['DROP_MONTH_NUMBER'] = $i;
			$output['DROP_MONTH_NAME'] = date('F', mktime(0,0,0,$i,1));
			$monthList[] = $output;
		}
		return $monthList;
	}

	function getYearList() {
		for($i=date('Y')-2; $i <= date('Y')+1; $i++) {
			$output['DROP_YEAR_NUMBER'] = $i;
			$yearList[] = $output;
		}
		return $yearList;
	}

	function createGroups($groupList, $eventID) {
		if (is_array($groupList)) {
			foreach ($groupList as $groupID) {
				$eventGroup = array('eventID'	=> $eventID,
									'groupID'	=> $groupID,
									);
				$this->db->insert('eventGroup', $eventGroup);
			}
		}
	}

	function createCampus($campusList, $eventID) {
		if (is_array($campusList)) {
			foreach ($campusList as $campusID) {
				$eventCampus = array('eventID'	=> $eventID,
									 'campusID'	=> $campusID,
									);
				$this->db->insert('eventCampus', $eventCampus);
			}
		}
	}

	function delPerms($eventID) {
		$this->db->delete('eventGroup', 'eventID', $eventID);
		$this->db->delete('eventCampus', 'eventID', $eventID);
	}

	function create($event) {

		if($this->getPerm($event) < WRITE_ACCESS)
			error(35);

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

        $cleanEvent = $this->getByID($eventID);

		if($this->getPerm($event['id']) < WRITE_ACCESS)
			error(34);

		$groupList = $event['groupList'];
		$campusList = $event['campusList'];

		unset($event['groupList']);
		unset($event['campusList']);

        /* Clear attendance if it has changed from true to false */
        if ( $event['attendance'] < $cleanEvent['attendance'] ) {
            $this->removeAttendanceByEvent($event['id']);
        }

		parent::update($event);

		$this->delPerms($event['id']);

		$this->createGroups($groupList, $event['id']);
		$this->createCampus($campusList, $event['id']);

		return $eventID;
	}

	function delete ($eventID) {
		$event = $this->getByID($eventID);

		if($this->getPerm($eventID) < WRITE_ACCESS)
			error(34);

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

