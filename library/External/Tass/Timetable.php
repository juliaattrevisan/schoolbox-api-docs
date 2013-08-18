<?php
/************************************************************
 *														   *
 *		 Copyright (c) 2008 Lektec Consulting				*
 *														   *
 *	This file provides access to synergetic timetable info	*
 *														   *
 ************************************************************/

class External_Tass_Timetable implements External_TimetableInterface {

	/**
	 * @var MSSQL
	 */
	private $database;

	function __construct() {
		define('TASS_tt_id', 13);

		$this->setDatabase(MSSQL::getInstance());
		$this->getDatabase()->setConvertEncoding(false);

		$this->days = $this->getDays();

		$this->groupHandler = new Group;
		$this->campusMapper = new \Schoolbox\CampusMapper();
	}

	function getCampusCode($campus) {

		$campus = $this->campusMapper->findByID($campus);
		return $campus->getExtTimetableCode();

	}

	function getDays() {
		$query = "SELECT cmpy_code, COUNT(*) as dayCount FROM ttdays WHERE tt_id = " . TASS_tt_id . " GROUP BY cmpy_code";
		$this->getDatabase()->query($query);

		while ($campus = $this->getDatabase()->getNextArray()) {
			$days[$campus['cmpy_code']] = $campus['dayCount'];
		}
		return $days;
	}

	function getTimetableDefinition($campus, $day = 0) {

		$campusCode = $this->getCampusCode($campus);
		/* Fetch the longest day to use as our day template...  */
		if($day == 0) {
            $query = "SELECT tt_id, day_code, prd_num FROM ttdays WHERE tt_id = " . TASS_tt_id . " AND cmpy_code = '$campusCode' ORDER BY prd_num DESC";
		    $longestDay = $this->getDatabase()->getRow($query);
            $day = $longestDay['day_code'];
        }

		$query = "SELECT prd_desc, prd_start, prd_end, prd_code, day_code FROM ttperiod
                WHERE tt_id = " . TASS_tt_id . " AND cmpy_code = '$campusCode' AND day_code='$day'
                ORDER BY prd_code";

		$this->getDatabase()->query($query);

		while ($period = $this->getDatabase()->getNextArray()) {
			preg_match('/([0-9]+):([0-9]+):[0-9]+:[0-9]+(AM|PM)/', $period['prd_end'], $TimeToMatches);
			preg_match('/([0-9]+):([0-9]+):[0-9]+:[0-9]+(AM|PM)/', $period['prd_start'], $TimeFromMatches);

			$timetableDef['desc'][] = $period['prd_desc'];
			$timetableDef['start'][] = (int)$TimeFromMatches[1] . ':' . $TimeFromMatches[2] . strtolower($TimeFromMatches[3]);
			$timetableDef['end'][] = (int)$TimeToMatches[1] . ':' . $TimeToMatches[2] . strtolower($TimeToMatches[3]);
			$timetableDef['number'][] = $period['prd_code'];
			$periodCount++;
		}

		$timetableDef['periodCount'] = $longestDay['prd_num'];
		$timetableDef['dayCount'] = $this->days[$campusCode];
		return $timetableDef;

	}

	//gets current day number
	function getDayNumber($campus, $date = 0) {

		if (!$date) {
			$date = mktime();
		}

		$campusCode = $this->getCampusCode($campus);

		if (date('w', $date) == 0 || date('w', $date) == 6) {
			return false;
		}

		//get day count
		if ($this->days[$campusCode] == 5) {
			return date("w", $date);
		}
		else {
			if ($this->days[$campusCode] == 7) {
				return 0;
			}
			else {
				if ($this->days[$campusCode] == 10) {

					if ((date('W', $date) % 2) == 0) {
						return date('w', $date);
					}
					else {
						return date('w', $date) + 5;
					}
				}
				else //unknown day number
				{
					return 0;
				}
			}
		}
	}


	//returns the period number for the current time
	function getPeriodNumber($campus) {
		return 0;
	}

	function getDayTimetable($synergy_id, $userID, $campus, $daynumber) {
		$timetabledef = $this->getTimetableDefinition($campus, $daynumber);

		if ($this->groupHandler->isStudent($userID)) {
			$query = "SELECT room_code, tch_code, ttmast.sub_code, ttmast.year_grp, ttmast.class, prd_code, sub_desc
                        FROM studsub
                        JOIN student ON studsub.stud_code = student.stud_code AND studsub.cmpy_code=student.cmpy_code
                        JOIN ttmast ON studsub.sub_code=ttmast.sub_code AND studsub.cmpy_code=ttmast.cmpy_code AND studsub.semester=ttmast.semester 
                                AND studsub.class=ttmast.class AND student.year_grp=ttmast.year_grp AND studsub.yr_study=ttmast.year_num
                        JOIN subtab ON subtab.sub_code=ttmast.sub_code AND subtab.cmpy_code=ttmast.cmpy_code
                        WHERE yr_study = '{$_SESSION['FileYear']}' AND studsub.semester = '{$_SESSION['FileSemester']}' 
                                AND studsub.stud_code = '$synergy_id'
                                AND day_code = '$daynumber' 
                        ORDER BY prd_code";
		}
		else {
			$query = "SELECT room_code, tch_code, ttmast.sub_code, year_grp, class, prd_code, sub_desc
                        FROM ttmast
                        JOIN subtab ON subtab.sub_code=ttmast.sub_code AND subtab.cmpy_code=ttmast.cmpy_code
                        WHERE year_num = '{$_SESSION['FileYear']}' AND semester = '{$_SESSION['FileSemester']}'
                            AND ttmast.cmpy_code = '{$this->getCampusCode($campus)}'
                            AND tch_code = '$synergy_id'
                            AND day_code = '$daynumber'
                         ORDER BY prd_code";
		}

		$periodCount = $timetabledef['periodCount'];
		$dayCount = $timetabledef['dayCount'];

		$result = $this->getDatabase()->query($query);
		//get first period
		if ($result !== FALSE && $result !== TRUE) {
			$desc = $this->getDatabase()->getNextArray($result);
		}
		else {
			$desc = "";
		}

		//for each period
		for ($x = 0; isset($timetabledef['desc'][$x]); $x++) {
			$timetable[$x]['periodName'] = $timetabledef['desc'][$x];
			$timetable[$x]['periodStart'] = $timetabledef['start'][$x];
			$timetable[$x]['periodEnd'] = $timetabledef['end'][$x];
			$timetable[$x]['periodNumber'] = $timetabledef['number'][$x];
			$timetable[$x]['periodSequence'] = 1;

			if ($timetabledef['sequence'][$x] != 2 && $desc['prd_code'] == $timetable[$x]['periodNumber']) {
				$timetable[$x]['description'] = $desc['sub_desc'];
				$timetable[$x]['code'] = trim($desc['year_grp'] . '-' . $desc['sub_code'] . '-' . $desc['class']);
				$timetable[$x]['room'] = $desc['room_code'];
				$desc = $this->getDatabase()->getNextArray();

				//bad data in synergetic.. why..?? 
				while ($desc['prd_code'] == $timetable[$x]['periodNumber'] && $desc != 0) {
					$desc = $this->getDatabase()->getNextArray();
				}
			}
		}


		return $timetable;

	}

	function getFullTimetable($synergy_id, $userID, $campus) {

		$timetabledef = $this->getTimetableDefinition($campus);

		if ($this->groupHandler->isStudent($userID)) {

			$query = "SELECT room_code, tch_code, ttmast.sub_code, ttmast.year_grp, ttmast.class, prd_code, sub_desc, day_code
                            FROM studsub
                            JOIN student ON studsub.stud_code = student.stud_code AND studsub.cmpy_code=student.cmpy_code
                            JOIN ttmast ON studsub.sub_code=ttmast.sub_code AND studsub.cmpy_code=ttmast.cmpy_code AND studsub.semester=ttmast.semester
                                    AND studsub.class=ttmast.class AND student.year_grp=ttmast.year_grp AND studsub.yr_study=ttmast.year_num
                            JOIN subtab ON subtab.sub_code=ttmast.sub_code AND subtab.cmpy_code=ttmast.cmpy_code
                            WHERE yr_study = '{$_SESSION['FileYear']}' AND studsub.semester = '{$_SESSION['FileSemester']}'
                                    AND studsub.stud_code = '$synergy_id'
                            ORDER BY prd_code, day_code";

		}
		else {

			$query = "SELECT room_code, tch_code, day_code, ttmast.sub_code, year_grp, class, prd_code, sub_desc
                        FROM ttmast
                        JOIN subtab ON subtab.sub_code=ttmast.sub_code AND subtab.cmpy_code=ttmast.cmpy_code
                        WHERE year_num = '{$_SESSION['FileYear']}' AND semester = '{$_SESSION['FileSemester']}'
                            AND ttmast.cmpy_code = '{$this->getCampusCode($campus)}'
                            AND tch_code = '$synergy_id'
                         ORDER BY prd_code, day_code";
		}

		$periodCount = $timetabledef['periodCount'];
		$dayCount = $timetabledef['dayCount'];

		$result = $this->getDatabase()->query($query);

		//get first period
		if ($result != 1) {
			$desc = $this->getDatabase()->getNextArray();
		}
		else {
			$desc = "";
		}

		//for each period
		for ($x = 0; isset($timetabledef['desc'][$x]); $x++) {
			$timetable[$x]['periodName'] = $timetabledef['desc'][$x];
			$timetable[$x]['periodStart'] = $timetabledef['start'][$x];
			$timetable[$x]['periodEnd'] = $timetabledef['end'][$x];
			$timetable[$x]['periodNumber'] = $timetabledef['number'][$x];
			$timetable[$x]['periodSequence'] = 1;

			//for each day
			for ($y = 1; $y <= $dayCount; $y++) {

				if ($timetabledef['sequence'][$x] != 2 && $desc['prd_code'] == $timetable[$x]['periodNumber'] && $desc['day_code'] == $y) {
					$timetable[$x]['day'][$y]['periodOn'] = 1;
					$timetable[$x]['day'][$y]['description'] = $desc['sub_desc'];
					$timetable[$x]['day'][$y]['code'] = trim($desc['year_grp'] . '-' . $desc['sub_code'] . '-' . $desc['class']);
					$timetable[$x]['day'][$y]['room'] = $desc['room_code'];
                    $timetable[$x]['day'][$y]['staffCode'] = $desc['tch_code'];
					$desc = $this->getDatabase()->getNextArray();

					//bad data in synergetic.. why..??
					while ($desc['day_code'] == $y && $desc['prd_code'] == $timetable[$x]['periodNumber']) {
						$desc = $this->getDatabase()->getNextArray();
					}

				}
				else {
					$timetable[$x]['day'][$y]['periodOn'] = 0;
				}

			}
		}
		return $timetable;
	}


	function getDaySubjects($synergy_id, $daynum) {
		$query = "SELECT Timetable.Room, SubjectClasses.Description,
                       	 Timetable.PeriodNumber, Timetable.ClassCode, SubjectClasses.ClassCampus
                         FROM SynergyOne.dbo.SubjectClasses SubjectClasses, SynergyOne.dbo.Timetable Timetable
                         WHERE Timetable.ClassCampus = SubjectClasses.ClassCampus AND
                         Timetable.ClassCode = SubjectClasses.ClassCode AND Timetable.FileSemester =
                         SubjectClasses.FileSemester AND Timetable.FileType = SubjectClasses.FileType
                         AND Timetable.FileYear = SubjectClasses.FileYear AND
                         ((SubjectClasses.FileSemester=" . $_SESSION['FileSemester'] . ") AND (SubjectClasses.FileYear='" . $_SESSION['FileYear'] . "')
                         AND (Timetable.StaffID='$synergy_id')) AND Timetable.DayNumber = '$daynum'
			  	ORDER BY Timetable.PeriodNumber";

		return $this->getDatabase()->getAll($query);

	}

	function getSubjectPeriod($code, $day) {
		//give a subject code and day this function returns the period that it occurs in

		$query = "SELECT Timetable.PeriodNumber
			FROM SynergyOne.dbo.Timetable Timetable
			WHERE Timetable.FileSemester='" . $_SESSION['FileSemester'] . "' AND Timetable.FileYear='" . $_SESSION['FileYear'] . "'
			AND Timetable.DayNumber = '$day' AND Timetable.ClassCode = '$code'
		";

		return $this->getDatabase()->getOne($query);

	}

	function getSubjectName($code) {

		$query = "SELECT SubjectClasses.Description
                       	FROM SynergyOne.dbo.SubjectClasses SubjectClasses
                       	WHERE SubjectClasses.ClassCode = '$code'";
		return $this->getDatabase()->getOne($query);
	}

	private function setDatabase($database) {
		$this->database = $database;
	}

	private function getDatabase() {
		return $this->database;
	}
}

?>
