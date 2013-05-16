<?php
/************************************************************
*                                                           *
* 		Copyright (c) 2008 Lektec Consulting				*
*                                                           *
*	This file provides access to Denbigh timetable info	*
*                                                           *
************************************************************/

class External_Denbigh_Timetable implements External_TimetableInterface {
	private $internalDatabase;

	private $externalDatabase;

	private $group;

	public function __construct () {
		/* Set internal database */
		$this->setInternalDatabase( Database::getInstance() );

		/* Establish connection to external database */
		$database = new Database();

		$hostName = Config::get('external_db_host');
		$databaseName = Config::get('external_db_db');
		$username = Config::get('external_db_user');
		$password = Config::get('external_db_passwd');

		$database->connect("mysql:host=$hostName;dbname=$databaseName", $username, $password);

		$this->setExternalDatabase($database);

		$this->setGroup( new Group );
	}

	public function getTimetableDefinition ($campusId, $day = 0) {
        $statement = $this->getInternalDatabase()->prepare("
			SELECT tc.start, tc.reset, tc.finish, tc.offset,
				ty.title, ty.cycle_length, ty.day_length, ty.regular
			FROM timetable_campus tc
			LEFT JOIN timetable_cycle ty ON ty.id = tc.cycle_id
			WHERE tc.campus_id = :campusId
			    AND tc.start < NOW()
			    AND tc.finish > NOW()
      		");

        $statement->execute( array(':campusId' => $campusId) );

        $definition = $statement->fetch(PDO::FETCH_ASSOC);

        /* Denbigh depends on a set day length */
        if ( !empty($definition['cycle_length']) && !empty($definition['day_length']) ) {
            /* Adapt to spec */
            $definition['dayCount'] = $definition['cycle_length'];
            $definition['periodCount'] = $definition['day_length'];

            return $definition;
        }
        else {
            return false;
        }
	}

	public function getDayNumber ($campusId, $date = 0) {
        if ( !$definition = $this->getTimetableDefinition($campusId) ) {
            return false;
        }

        if ( empty($date) ) {
            $date = time();
        }

        if ( empty($definition['reset']) ) {
            $start = strtotime($definition['start']);
        }
        else {
            $start = strtotime($definition['reset']);
        }

        $days = ceil( ($date - $start) / (60 * 60 * 24) ) + 1;

        /* Calculate the business days from the even weeks */
        $businessDays = 5 * floor($days / 7);

        /* Handle the excess days */
        $excessDays = $days % 7;

        for ( $i = 0; $i < $excessDays; $i++ ) {
            $currentExcessDay = $date - (60 * 60 * 24 * $i);

            /* If its not Saturday or Sunday then increment the business days */
            if ( date('N', $currentExcessDay) < 6 ) {
                $businessDays++;
            }
        }

        if ( is_numeric($definition['offset']) ) {
            $businessDays += $definition['offset'];
        }

        $currentDay = $businessDays % $definition['cycle_length'];

        if($currentDay == 0)
            $currentDay = $definition['cycle_length'];

        return $currentDay;
	}

	public function getPeriodNumber ($campusId) {
		return 0;
	}

	public function getFullTimetable ($externalUserId, $userId, $campusId) {
		/* Setup skeleton timetable */
        if ( !$definition = $this->getTimetableDefinition($campusId) ) {
            return false;
        }

		$periodList = array();

		for ( $i = 1; $i <= $definition['periodCount'];  $i++ ) {
			$periodList[$i] = array(
				'periodNumber'   => $i,
				'periodName'     => "Period $i",
				'periodStart'    => '',
				'periodEnd'      => '',
				'periodSequence' => '1',
				'day'            => array()
			);

			for ( $j = 1; $j <= $definition['dayCount'];  $j++ ) {
				$periodList[$i]['day'][$j] = array(
					'periodOn' => 0
				);
			}
		}

		/* Fill timetable */
		if ( $this->getGroup()->isStaff($userId) ) {
			$statement = $this->getExternalDatabase()->prepare("
				SELECT l.day, l.period, l.classCode, l.staffCode, l.room, co.subjectName
				FROM People p
				RIGHT JOIN Staff s ON s.peopleDenbighID = p.denbighID
				RIGHT JOIN Lessons l ON l.staffCode = s.staffCode
				RIGHT JOIN Classes cl ON cl.classCode = l.classCode
				RIGHT JOIN Courses co ON co.courseCode = cl.courseCode
				WHERE p.denbighID = :externalUserId
			");
		}
		else {
			$statement = $this->getExternalDatabase()->prepare("
				SELECT l.day, l.period, l.classCode, l.staffCode, l.room, co.subjectName
				FROM People p
				RIGHT JOIN Enrolments e ON e.studentID = p.denbighID
				RIGHT JOIN Lessons l ON l.classCode = e.classCode
				RIGHT JOIN Classes cl ON cl.classCode = l.classCode
				RIGHT JOIN Courses co ON co.courseCode = cl.courseCode
				WHERE p.denbighID = :externalUserId
			");
		}

		$statement->bindParam(':externalUserId', $externalUserId, PDO::PARAM_INT);
		$statement->execute();

		$lessonList = $statement->fetchAll(PDO::FETCH_ASSOC);

		if ( !empty($lessonList) ) {
			foreach ( $lessonList as $lesson ) {

				$period = $lesson['period'];
				$day = $lesson['day'];

				if ( isset($periodList[$period]['day'][$day]) ) {
					$periodList[$period]['day'][$day] = array(
						'periodOn'    => 1,
						'description' => $lesson['subjectName'],
						'code'        => $lesson['classCode'],
						'room'        => $lesson['room']
					);
				}
					
			}
		}

		return $periodList;
	}

	public function getDayTimetable ($externalUserId, $userId, $campusId, $dayNumber) {
		/* Setup skeleton timetable */
        if ( !$definition = $this->getTimetableDefinition($campusId) ) {
            return false;
        }

		$periodList = array();

		for ( $i = 1; $i <= $definition['periodCount'];  $i++ ) {
			$periodList[$i] = array(
				'periodNumber'   => $i,
				'periodName'     => "Period $i",
				'periodStart'    => '',
				'periodEnd'      => '',
				'periodSequence' => '1',
			);
		}

		/* Fill timetable */
		if ( $this->getGroup()->isStaff($userId) ) {
			$statement = $this->getExternalDatabase()->prepare("
				SELECT l.day, l.period, l.classCode, l.staffCode, l.room, co.subjectName
				FROM People p
				RIGHT JOIN Staff s ON s.peopleDenbighID = p.denbighID
				RIGHT JOIN Lessons l ON l.staffCode = s.staffCode
				RIGHT JOIN Classes cl ON cl.classCode = l.classCode
				RIGHT JOIN Courses co ON co.courseCode = cl.courseCode
				WHERE p.denbighID = :externalUserId
					AND l.day = :dayNumber
			");
		}
		else {
			$statement = $this->getExternalDatabase()->prepare("
				SELECT l.day, l.period, l.classCode, l.staffCode, l.room, co.subjectName
				FROM People p
				RIGHT JOIN Enrolments e ON e.studentID = p.denbighID
				RIGHT JOIN Lessons l ON l.classCode = e.classCode
				RIGHT JOIN Classes cl ON cl.classCode = l.classCode
				RIGHT JOIN Courses co ON co.courseCode = cl.courseCode
				WHERE p.denbighID = :externalUserId
					AND l.day = :dayNumber
			");
		}

		$statement->bindParam(':externalUserId', $dayNumber);
		$statement->bindParam(':dayNumber', $dayNumber, PDO::PARAM_INT);
		$statement->execute();

		$lessonList = $statement->fetchAll(PDO::FETCH_ASSOC);

		if ( !empty($lessonList) ) {
			foreach ( $lessonList as $lesson ) {

				$period = $lesson['period'];

				if ( isset($periodList[$period]) ) {
					$periodList[$period] = array_merge($periodList[$period], array(
						'description' => $lesson['subjectName'],
						'code'        => $lesson['classCode'],
						'room'        => $lesson['room']
					));
				}

			}
		}

		return $periodList;
	}

	public function getDaySubjects ($externalUserId, $dayNumber) {

	}

	public function getSubjectPeriod($classCode, $dayNumber) {

	}
	
	public function getSubjectName($classCode) {

	}
	
	/**
	 * @param Database $database
	 * @return void
	 */
	private function setInternalDatabase (Database $internalDatabase) {
		$this->internalDatabase = $internalDatabase;
	}

	/**
	 * @return Database
	 */
	private function getInternalDatabase () {
		return $this->internalDatabase;
	}
	
	/**
	 * @param Database $database
	 * @return void
	 */
	private function setExternalDatabase (Database $externalDatabase) {
		$this->externalDatabase = $externalDatabase;
	}

	/**
	 * @return Database
	 */
	private function getExternalDatabase () {
		return $this->externalDatabase;
	}

	/**
	 * @param Group $group
	 * @return void
	 */
	private function setGroup (Group $group) {
		$this->group = $group;
	}

	/**
	 * @return Group
	 */
	private function getGroup () {
		return $this->group;
	}
}

?>
