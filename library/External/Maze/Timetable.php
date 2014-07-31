<?php
/**
 * External_Maze_Timetable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_Maze_Timetable implements External_TimetableInterface {
	/**
	 * An instance of MSSQL
	 *
	 * @var null
	 */
	private $db = null;

	/**
	 * Helper
	 *
	 * @var null
	 */
	private $helper = null;

	/**
	 * An instance of CampusMapper
	 *
	 * @var null
	 */
	private $campusMapper = null;

	/**
	 * An instance of Group
	 *
	 * @var Group
	 */
	private $groupHandler = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setDb(MSSQL::getInstance());
		$this->setHelper(new External_Maze_Helper());
		$this->setCampusMapper(new \Schoolbox\CampusMapper());
		$this->setGroupHandler(new Group());
	}

	/**
	 * Array key
	 *
	 * @var string
	 */
	private $timetablePeriodLabel = 'TH_RLABEL';

	/**
	 * Array key
	 *
	 * @var string
	 */
	private $timetableDayLabel = 'TH_CLABEL';

	/**
	 * The headings of the timetable, going horizontally
	 * from left to right containing all the times and day numbers
	 *
	 * @param $campusId
	 * @param int $day
	 * @return array
	 */
	public function getTimetableDefinition($campusId, $day=0) {
		$query = "
			SELECT TOP 2 TH.*
			FROM TH
			WHERE THKEY = '{$this->getHelper()->getCurrentTTPeriod()}'
		";

		$result = $this->getDb()->getAll($query);

		$timetableDef = array();

		if (count($result) && is_array($result)) {
			$definition = $result[0];

			for ($i = 1; $i <= $definition['THROWS']; $i++) {
				$dayTempKey = $this->getTimetableDayLabel($i);

				/* Day-specific e.g. for Dashboard
				 */
				if (!$day || "Day {$day}" == $definition[$dayTempKey]) {

					/* TH_RLABEL01, TH_RLABEL02...TH_RLABEL13
					 */
					$periodTempKey = $this->getTimetablePeriodLabel($i);

					/* Ram it in
					 */
					$timetableDef['desc'][] = $definition[$periodTempKey];
					$timetableDef['start'][] = '';
					$timetableDef['end'][] = '';
					$timetableDef['number'][] = $i;
					$timetableDef['sequence'][] = $i;
				}
			}
		}

		return $timetableDef;
	}

	/**
	 * Get timetable period label
	 *
	 * @param $i
	 * @return string
	 */
	private function getTimetablePeriodLabel($i) {
		return $this->timetablePeriodLabel . ($i < 10 ? 0 . $i : $i);
	}

	/**
	 * Get timetable day label
	 *
	 * @param $i
	 * @return string
	 */
	private function getTimetableDayLabel($i) {
		return $this->timetableDayLabel . ($i < 10 ? 0 . $i : $i);
	}

	/* Getters/Setters
	 */

	/**
	 * Set timetable period label
	 *
	 * @param string $timetablePeriodLabel
	 */
	private function setTimetablePeriodLabel($timetablePeriodLabel) {
		$this->timetablePeriodLabel = $timetablePeriodLabel;
	}

	/**
	 * Set timetable day label
	 *
	 * @param string $timetableDayLabel
	 */
	private function setTimetableDayLabel($timetableDayLabel) {
		$this->timetableDayLabel = $timetableDayLabel;
	}

	/**
	 * Get the current day number
	 *
	 * @param $campusId
	 * @param int $date
	 * @return bool
	 */
	public function getDayNumber($campusId, $date = 0) {
		$currentDateString = date('M d Y');

		$query = "
			SELECT TOP 1 QCOL
			FROM TCTQ
			INNER JOIN TH ON TCTQ.QKEY = TH.THKEY
			WHERE TCTQKEY LIKE '{$currentDateString}%'
		";

		$result = $this->getDb()->getRow($query);

		return $result['QCOL'];
	}

	public function getTest() {
		$query = "
			SELECT
			 	THKEY,
			 	CALENDAR_START01,
			 	CALENDAR_START02,
			 	CALENDAR_START03,
			 	CALENDAR_START04,
			 	CALENDAR_END01,
			 	CALENDAR_END02,
			 	CALENDAR_END03,
			 	CALENDAR_END04
			FROM TH
			WHERE THKEY LIKE '2014%'
			ORDER BY THKEY DESC
		";
	}

	/**
	 * Calculates the current period based on the current time
	 *
	 * The returned data structure is required for Dashboard Timetable Controller
	 *
	 * @param $campusId
	 * @return int
	 */
	public function getPeriodNumber($campusId) {
		$now = new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

		$allTimes = $this->getPeriodTimes();

		if (is_array($allTimes) && count($allTimes)) {
			$i = 1;

			foreach($allTimes as $currentTime) {
				if ($currentTime['startTime']->getTimeOnly() <= $now->getTimeOnly()) {
					if ($currentTime['endTime']->getTimeOnly() > $now->getTimeOnly()) {

						return array(
							'PeriodNumber' => $i
						);
					}
				}

				$i++;
			}
		}

		return array(
			'PeriodNumber' => 0
		);
	}

	/**
	 * Return a user's timetable for a specific day
	 *
	 * @param $externalUserId
	 * @param $userId
	 * @param $campusId int ignored
	 * @param $dayNumber
	 * @return array|bool
	 */
	public function getDayTimetable($externalUserId, $userId, $campusId, $dayNumber) {
		if ($this->getGroupHandler()->isStudent($userId)) {
			return $this->getStudentTimetable($externalUserId, $dayNumber);
		}
		else if ($this->getGroupHandler()->isStaff($userId)) {
			return $this->getStaffTimetable($externalUserId, $dayNumber);
		}
	}

	/**
	 * Spawn a staff timetbale for a particular user and day number
	 *
	 * @param $externalUserId
	 * @param $dayNumber
	 * @return array
	 */
	public function getStaffTimetable($externalUserId, $dayNumber) {

		/* Get staff member's KLPID
		 */
		$query = "
			SELECT KLPID
			FROM SF
			WHERE SFKEY = '{$externalUserId}'
		";

		$result1 = $this->getDb()->getRow($query);

		$klpId = $result1['KLPID'];

		/* Get all the periods (I think this sql stored proc is somehow not working)
 		 */
		$vars = array(
			'klp_GetTimetable',
			array(
				array('@Today', date('Y-m-d h:i:s'), SQLVARCHAR, false, false, -1),
				array('@ReturnClassPeriodsOnly', 'Y', SQLVARCHAR, false, false, -1),
			)
		);

		$result2 = $this->getDb()->executeProcedure($vars[0], $vars[1]);

		/* A list of all the class values, which are stma.idents
		 */
		$allSubjects = array();
		$allObjects = array();

		if (is_array($result2['result']) && count($result2['result'])) {
			foreach($result2['result'] as $currentPeriod) {
				if ($currentPeriod['staffid'] == $klpId) {
					$allObjects[] = $currentPeriod;

					$allSubjects[] = $currentPeriod['class'];
				}
			}
		}

		$allSubjects = '(\'' . implode('\',\'', $allSubjects) . '\')';

		/* Find all the STMAs that are in $allSubjects and rearrange
		 */
		$query = "
			SELECT *
			FROM STMA
			WHERE STMA.IDENT IN {$allSubjects}
		";

		$allStma = $this->getDb()->getAll($query);

		$processedStma = array();

		if (is_array($allStma) && count($allStma)) {
			foreach($allStma as $currentStma) {
				$processedStma[$currentStma['IDENT']] = $currentStma;
			}
		}

		/* Get the timetable definition
		 */
		$vars = array(
			'klp_GetTimetableStructure',
			array(
				array('@Today', null, SQLVARCHAR, false, true, -1),
				array('@ReturnClassPeriodsOnly', 'Y', SQLVARCHAR, false, false, -1),
			)
		);

		$result3 = $this->getDb()->executeProcedure($vars[0], $vars[1]);

		/* Invert everything in the timetable definition
		 */
		$allStructures = array();

		foreach($result3['result'] as $currentTimetableStructure) {

			/* Restrict to a specified Day
			 * Otherwise skip it
			 */
			if ($currentTimetableStructure['DayNo'] == $dayNumber) {

				/* Clone that guy out
				 */
				$structure = $currentTimetableStructure;
				$structure['class']['subject'] = array();

				/* Find the subjects which have this period number
				 * This class occurs in $currentTimetableStructure
				 */
				if (is_array($allObjects) && count($allObjects)) {
					foreach($allObjects as $currentObject) {
						if ($currentTimetableStructure['PeriodNo'] == $currentObject['periodno']) {

							/* Separate subject identifier
							 */
							$subjectData = $processedStma[$currentObject['class']];

							/* Assemble
							 */
							$class = $currentObject;

							$class['subject']['code'] = $this->getHelper()->generateClassCode($subjectData['MKEY'], $subjectData['CLASS']);
							$class['subject']['name'] = $this->getSubjectName($subjectData['MKEY']);

							/* Stack up
							 */
							$structure['class'] = $class;
						}
					}
				}

				/* Stack up
				 */
				$allStructures[] = $structure;
			}
		}

		/* Postprocess for Dashboard compatibility
		 */
		$finalTimetable = array();
		$times = $this->getPeriodTimes();
		$i = 0;

		foreach($allStructures as $currentStructure) {

			/* For the dashboard which alternates between heading and data
			 */
			$finalTimetable[] = array(
				'periodName' => $currentStructure['PerNoDesc'],
				'periodStart' => $times[$i]['startTime']->getTimeOnly(),
				'periodEnd' => $times[$i]['endTime']->getTimeOnly(),
				'periodNumber' => $currentStructure['CalRow'],
				'periodSequence' => 1,
				'description' => $currentStructure['class']['subject']['name'],
				'code' => $currentStructure['class']['subject']['code'],
				'room' => $currentStructure['class']['room'],
				'staffID' => '',
			);

			$i++;
		}

		/* TODO: Some serious assumption about final period 0
		 * Truncate the final Period 0, last in the $finalTimetable.
		 * It follows Period 7 right out of the stored proc
		 */
		array_pop($finalTimetable);

		return $finalTimetable;
	}

	/**
	 * Return a student's timetable for a specific day
	 *
	 * @param $externalUserId
	 * @param $dayNumber
	 * @return mixed
	 */
	public function getStudentTimetable($externalUserId, $dayNumber) {

		/* Get all the subjects for the current ttperiod
		 */
		$query = "
			SELECT *
			FROM STMA
			WHERE SKEY = '{$externalUserId}'
			AND TTPERIOD = '{$this->getHelper()->getCurrentTTPeriod()}'
		";

		$result1 = $this->getDb()->getAll($query);

		/* Get all the periods (I think this sql stored proc is somehow not working)
		 */
		$vars = array(
			'klp_GetTimetable',
			array(
				array('@Today', date('Y-m-d h:i:s'), SQLVARCHAR, false, false, -1),
				array('@ReturnClassPeriodsOnly', 'Y', SQLVARCHAR, false, false, -1),
			)
		);

		$result2 = $this->getDb()->executeProcedure($vars[0], $vars[1]);

		/* Get the timetable definition
		 */
		$vars = array(
			'klp_GetTimetableStructure',
			array(
				array('@Today', null, SQLVARCHAR, false, true, -1),
				array('@ReturnClassPeriodsOnly', 'Y', SQLVARCHAR, false, false, -1),
			)
		);

		$result3 = $this->getDb()->executeProcedure($vars[0], $vars[1]);

		/* Manual join
		 * For every STMA record, find the STMA.IDENT
		 * Find all the GetTimetable.class records that match
		 * Find all the PeriodNo's that GetTimetable.class belongs in
		 */
		$allObjects = array();

		/* Reduce data sets. This generates a list of subjects and the periods
		 * in which they appear stored as an array
		 */
		if (is_array($result1) && count($result1)) {
			foreach($result1 as $currentStma) {
				$object = array();
				$object['stma'] = $currentStma;
				$object['period'] = array();

				/* This tends to be a big data set
				 */
				foreach($result2['result'] as $currentPeriod) {
					if ($currentStma['IDENT'] == $currentPeriod['class']) {
						$object['period'][$currentPeriod['periodno']] = $currentPeriod;
					}
				}

				$allObjects[] = $object;
			}
		}

		/* Invert everything in the timetable definition
		 */
		$allStructures = array();

		foreach($result3['result'] as $currentTimetableStructure) {

			/* Restrict to a specified Day
			 * Otherwise skip it
			 */
			if ($currentTimetableStructure['DayNo'] == $dayNumber) {

				/* Clone that guy out
				 */
				$structure = $currentTimetableStructure;
				$structure['class']['subject'] = array();
				$structure['class']['periods'] = array();

				/* Find the subjects which have this period number
				 * This class occurs in $currentTimetableStructure
				 */
				if (is_array($allObjects) && count($allObjects)) {
					foreach($allObjects as $currentObject) {
						if (in_array($currentTimetableStructure['PeriodNo'], array_keys($currentObject['period']))) {

							/* Assemble
							 */
							$class = $currentObject['period'][$currentTimetableStructure['PeriodNo']];

							/* Separate subject identifier
							 */
							$class['subject']['code'] = $this->getHelper()->generateClassCode($currentObject['stma']['MKEY'], $currentObject['stma']['CLASS']);
							$class['subject']['name'] = $this->getSubjectName($currentObject['stma']['MKEY']);

							/* Stack up
							 */
							$structure['stmaIdent'] = $currentObject['stma']['IDENT'];
							$structure['class'] = $class;
						}
					}
				}

				/* Stack up
				 */
				$allStructures[] = $structure;
			}
		}

		/* Postprocess for Dashboard compatibility
 		 */
		$times = $this->getPeriodTimes();
		$finalTimetable = array();
		$i = 0;

		foreach($allStructures as $currentStructure) {

			/* For the dashboard which alternates between heading and data
			 */
			$finalTimetable[] = array(
				'periodName' => $currentStructure['PerNoDesc'],
				'periodStart' => $times[$i]['startTime']->getTimeOnly(),
				'periodEnd' => $times[$i]['endTime']->getTimeOnly(),
				'periodNumber' => $currentStructure['CalRow'],
				'periodSequence' => 1,
				'description' => $currentStructure['class']['subject']['name'],
				'code' => $currentStructure['class']['subject']['code'],
				'room' => $currentStructure['class']['room'],
				'staffID' => '',
			);

			$i++;
		}

		return $finalTimetable;
	}

	/**
	 * Get times for when periods begin and end
	 *
	 * @return array|bool
	 */
	private function getPeriodTimes() {

		/* TODO: Some serious assumption about 14 here
		 */
		$query = "
			SELECT TOP 14
				CONVERT(VARCHAR, TIME_START, 120) AS startTime,
				CONVERT(VARCHAR, TIME_END, 120) AS endTime
			FROM TCTD
			WHERE QKEY = '{$this->getHelper()->getCurrentTTPeriod()}'
		";

		$result = $this->getDb()->getAll($query);

		$processed = array();

		if (is_array($result) && count($result)) {
			foreach($result as $currentResult) {
				$processed[] = array(
					'startTime' => new \Alaress_Date_Format($currentResult['startTime'], 'y-m-d h:i:s'),
					'endTime' => new \Alaress_Date_Format($currentResult['endTime'], 'y-m-d h:i:s'),
				);
			}
		}

		return $processed;
	}

	/**
	 * Return the ENTIRE timetable for a user
	 *
	 * @param $externalUserId
	 * @param $userId
	 * @param $campusId
	 * @return array|bool
	 */
	public function getFullTimetable($externalUserId, $userId, $campusId) {
		$timetable = array();
		$allDayTimetables = array();

		/* This person's campus
		 */
		$campus = $this->getCampusMapper()->findById($campusId);

		/* Get all the days' timetables first
		 */
		for ($day = 1; $day <= $campus->getTimetableDay(); $day++) {
			$allDayTimetables[] = $this->getDayTimetable($externalUserId, $userId, $campusId, $day);
		}

		/* Assemble into a 2D array
		 * Each row represents a Period across each Day in the cycle
		 *
		 * [Period1 [Day1, Day2, Day3, DayN...]]
		 * [Period2 [Day1, Day2, Day3, DayN...]]
		 * [Period3 [Day1, Day2, Day3, DayN...]]
		 * [PeriodN [Day1, Day2, Day3, DayN...]]
		 * ...
		 */
		for ($period = 0; $period < count($allDayTimetables[0]); $period++) {
			$timetable[$period]['periodName'] = $allDayTimetables[0][$period]['periodName'];
			$timetable[$period]['periodStart'] = $allDayTimetables[0][$period]['periodStart'];
			$timetable[$period]['periodEnd'] = $allDayTimetables[0][$period]['periodEnd'];

			/* Put in Day classes
			 */
			for ($day = 0; $day < $campus->getTimetableDay(); $day++) {
				$timetable[$period]['day'][$day] = array(
					'periodOn' => '',
					'description' => $allDayTimetables[$day][$period]['description'],
					'code' => $allDayTimetables[$day][$period]['code'],
					'room' => $allDayTimetables[$day][$period]['room'],
					'staffID' => '',
					'staffFirstname' => '',
					'staffSurname' => '',
					'staffTitle' => '',
				);
			}

		}

		return $timetable;
	}

	/**
	 * Retrieve the list of subjects for a user on a particular day
	 *
	 * @param $externalUserId
	 * @param $dayNumber
	 * @return bool
	 */
	public function getDaySubjects($externalUserId, $dayNumber) {
	}

	/**
	 * give a subject code and day this function returns the period that it occurs in
	 * Assumption made that this should be the sequence rather than period name
	 *
	 * @param $classCode
	 * @param $dayNumber
	 * @return int|bool
	 */
	public function getSubjectPeriod($classCode, $dayNumber) {
	}

	/**
	 * Get the subject name given a string
	 *
	 * @param $classCode
	 * @return bool
	 */
	public function getSubjectName($classCode) {
		$query = "
		 	SELECT TOP 1 FULLNAME
		 	FROM SU
		 	WHERE SUKEY = '{$classCode}'
		 ";

		$subject = $this->getDb()->getRow($query);

		if ($subject) {
			return $subject['FULLNAME'];
		}

		return false;
	}

	/**
	 * @todo Implement for MAZE connector
	 *
	 * @param  string $roomCode
	 * @return array
	 */
	public function getRoomTimetable($roomCode) {
		// I'm not even going to try navigating this MAZE
		return array();
	}

	/* Getters/Setters
	 */

	/**
	 * Set db
	 *
	 * @param null $db
	 */
	private function setDb($db) {
		$this->db = $db;
	}

	/**
	 * Get db
	 *
	 * @return MSSQL
	 */
	private function getDb() {
		return $this->db;
	}

	/**
	 * Set helper
	 *
	 * @param null $helper
	 */
	private function setHelper($helper) {
		$this->helper = $helper;
	}

	/**
	 * Get helper
	 *
	 * @return External_Maze_Helper
	 */
	private function getHelper() {
		return $this->helper;
	}

	/**
	 * Set campus mapper
	 *
	 * @param null $campusMapper
	 */
	private function setCampusMapper($campusMapper) {
		$this->campusMapper = $campusMapper;
	}

	/**
	 * Get campus mapper
	 *
	 * @return \Schoolbox\CampusMapper
	 */
	private function getCampusMapper() {
		return $this->campusMapper;
	}

	/**
	 * Set group handler
	 *
	 * @param \Group $groupHandler
	 */
	private function setGroupHandler($groupHandler) {
		$this->groupHandler = $groupHandler;
	}

	/**
	 * Get group handler
	 *
	 * @return \Group
	 */
	private function getGroupHandler() {
		return $this->groupHandler;
	}
}