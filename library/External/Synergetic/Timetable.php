<?php
/************************************************************
*                                                           *
* 		Copyright (c) 2008 Lektec Consulting				*
*                                                           *
*	This file provides access to synergetic timetable info	*	    
*                                                           *
************************************************************/

use Alaress\DebugBar\Instance as DebugBar;

class External_Synergetic_Timetable implements External_TimetableInterface {

	/**
	 * @var MSSQL $database
	 */
	private $database;

	protected $synergeticDay = array();

	function __construct () {
		$this->setDatabase( new MSSQL() );
		if (DebugBar::isEnabled()) {
			$this->getDatabase()->installCollector();
			DebugBar::getInstance()->addCollector($this->getDatabase()->getCollector());
		}
		$this->groupHandler = new Group;

		$this->campusMapper = new \Schoolbox\CampusMapper();


	}

	function getTimetableDefinition($campus, $day = 0) {

		$campusModel = $this->campusMapper->findById($campus);  //TODO campus object should be passed
		if (!($campusModel instanceof \Schoolbox\Campus)) {
			return array();
		}

		$query = "SELECT TimetableDefinition.PeriodNumber, TimetableDefinition.PeriodNumberSeq, TimetableDefinition.TimeFrom,
					TimetableDefinition.TimeTo, TimetableDefinition.Description, TimetableDefinition.DayNumber
					FROM SynergyOne.dbo.TimetableDefinition TimetableDefinition
					WHERE TimetableDefinition.FileYear='{$_SESSION['FileYear']}' AND
						TimetableDefinition.FileSemester='{$_SESSION['FileSemester']}' AND
						TimetableDefinition.TimetableGroup='{$campusModel->getExtTimetableCode()}' AND
						(TimetableDefinition.DayNumber = '$day' )
					ORDER BY TimetableDefinition.PeriodNumber, TimetableDefinition.PeriodNumberSeq
					";
		$this->getDatabase()->query($query);

		$periodCount = 0;

		while($period = $this->getDatabase()->getNextArray() ) {
			preg_match('/([0-9]+):([0-9]+):[0-9]+:[0-9]+(AM|PM)/', $period['TimeTo'], $TimeToMatches);
			preg_match('/([0-9]+):([0-9]+):[0-9]+:[0-9]+(AM|PM)/', $period['TimeFrom'], $TimeFromMatches);

			$timetableDef['desc'][] = $period['Description'];
			$timetableDef['start'][] = (int)$TimeFromMatches[1].':'.$TimeFromMatches[2].strtolower($TimeFromMatches[3]);
			$timetableDef['end'][] = (int)$TimeToMatches[1].':'.$TimeToMatches[2].strtolower($TimeToMatches[3]);
			$timetableDef['number'][] = $period['PeriodNumber'];
			$timetableDef['sequence'][] = $period['PeriodNumberSeq'];
			$periodCount++;
		}
        
        //Fall back to day 0 if nothing found
        if($periodCount == 0 && $day != 0) {
            return $this->getTimetableDefinition(($campus));
        }

		$timetableDef['periodCount'] = $periodCount;
		$timetableDef['dayCount'] = $campusModel->getTimetableDay();
		return $timetableDef;

	}

	//gets current day number
	function getDayNumber($campus, $date = 0) {

		$campusModel = $this->campusMapper->findById($campus);  //TODO campus object should be passed
		if (!($campusModel instanceof \Schoolbox\Campus)) {
			return false;
		}
		$days = $campusModel->getTimetableDay();

		if(!$date) {
			$date = mktime();
		}

		if(date('w', $date) == 0 || date('w', $date) == 6)
			return false;



		// if operating on a 5-day timetable
		if($days == 5) {
			$day = date("w", $date);
            if($day < 1 || $day > 5)
                $day = 1;

            // calc offset
            $startDate = $this->getLastSynergeticDay($date, $campus);
            if($startDate) {
                $offset = ($this->synergeticDay[$startDate]-1) + (date('N', $startDate) + 4);

                // timetable does not start on a Monday: calculate the offset for display
                $today = ($day+$days) - $offset;


                // if offset pushes "today" back further than the diaplayed week, wraparound
                while($today <= 0) {
                    $today += $days;
                }

                // if offset is negative, mod to bring back into this week
                if($today > $days)
                    $today %= $days;

                return $today;
            }
            else {
                return $day;
            }

        }
        // if operating on a 7-day timetable
		else if($days == 7) {

			$startDate = $this->getLastSynergeticDay($date, $campus);
		
			//find number of days between both dates
			$difference = intval(($date - $startDate) / 86400);

			//find number of weekends between 2 dates
			$weekends = intval(($difference+date("w", $startDate)) / 7);

			$currentDay = ($this->synergeticDay[$startDate] + $difference - 2 * $weekends - 1) % 7 + 1;

			//make correction for sunday (otherwise we travel back in time)
			if(date("w", $date) == 0)
				$currentDay = $currentDay % 7 + 1;

			return $currentDay;
		}
        // if operating on a 10-day timetable
		else if($days == 10) {

            $startDate = $this->getLastSynergeticDay($date, $campus);

            if($this->synergeticDay[$startDate] > 5)
                $this->synergeticDay[$startDate] = $this->synergeticDay[$startDate] + 1;  //extra days for weekend

            $startDate = $startDate - ($this->synergeticDay[$startDate]*86400);

            if(!$startDate) //Just assume the start of the year if we cannot find anything
                $startDate = mktime(0,0,0,1,1,date('Y'));

            //find number of days between both dates
            $difference = floor(($date - $startDate) / 86400);
            //find number of weeks between 2 dates
            $weeks = intval(($difference) / 7);

            if(($weeks % 2) == 0) {
                    $day =  date('w',$date);
                    if($day < 1) //on sundays move it forward
                        $day = 1;
            }
            else {
                    $day =  date('w',$date) + 5;
                    if($day < 6) //on sundays move it forward
                        $day = 6;

            }
            return $day;
		}
		else	//unknown day number 
			return 0;
	}


	function getLastSynergeticDay($date, $campus){
			//This function gets all start of term days entered into synergetic from the date provided backwards.
			//It caches this info so you can get it later again.
			//Be careful if you call this function with a date in the past then in the future it may cause an error
			//TODO:  Make function cache entire year then have better smarts at working out which day to use.

			//date format yyyy-dd-mm 00:00:00

			//Access cache first
			if(!empty($this->synergeticDay)) {
				foreach($this->synergeticDay as $tdate => $tday) {
					if($date > $tdate) {
						return $tdate;
					}
				}
			}

			$campusModel = $this->campusMapper->findById($campus);  //TODO campus object should be passed
			if (!($campusModel instanceof \Schoolbox\Campus)) {
				return false;
			}
			$days = $campusModel->getTimetableDay();

			$nowmssqldate = date('Y-m-d',$date).' 00:00:00';

            /* First attempt to get calendar dates based on campus */

			$query = "SELECT *, CONVERT(CHAR(19),CalendarEvents.CalendarDate) as startDate, CalendarEvents.CalendarType as type
			FROM SynergyOne.dbo.CalendarEvents CalendarEvents
			WHERE (CalendarEvents.CalendarType LIKE '".$campusModel->getExtTimetableCode()."_D%') AND CalendarEvents.CalendarDate <= '$nowmssqldate'
			ORDER BY CalendarEvents.CalendarDate DESC";

			$result = $this->getDatabase()->query($query);
			//Cache all date and days into variable
			while($day = $this->getDatabase()->getNextArray($result)) {

				//cut out d from CalenderType string
				preg_match('/d([0-9]+)/i', $day['type'], $match);

                if($match[1] > 0 AND $match[1] <= $days ) {
                    $this->synergeticDay[strtotime($day['startDate'])] = $match[1];
                }
			}

			//yes this is meant to get the first element and return it
			if(!empty($this->synergeticDay)) {
                foreach($this->synergeticDay as $date => $day){
					return $date;
				}
			} else {

                /*  Else get calendar dates from the general pool   */

                $query = "SELECT *, CONVERT(CHAR(19),CalendarEvents.CalendarDate) as startDate, CalendarEvents.CalendarType as type
                FROM SynergyOne.dbo.CalendarEvents CalendarEvents
                WHERE (CalendarEvents.CalendarType LIKE 'D%') AND CalendarEvents.CalendarDate <= '$nowmssqldate'
                ORDER BY CalendarEvents.CalendarDate DESC";

                $result = $this->getDatabase()->query($query);
                //Cache all date and days into variable
                while($day = $this->getDatabase()->getNextArray($result)) {
                    //cut out d from CalenderType string
				    preg_match('/d([0-9]+)/i', $day['type'], $match);
                    if($match[1] > 0 AND $match[1] <= $days ) {
                        $this->synergeticDay[strtotime($day['startDate'])] = $match[1];
                    }
                }

               if(!empty($this->synergeticDay)) {
				foreach($this->synergeticDay as $date => $day){
					return $date;
				}
                }
            }

	}

	//returns the period number for the current time
	function getPeriodNumber($campus)
	{
		$campusModel = $this->campusMapper->findById($campus);  //TODO campus object should be passed
		if (!($campusModel instanceof \Schoolbox\Campus)) {
			return false;
		}
		$campusString = $campusModel->getExtTimetableCode();

		# Since the year information in the TimeFrom and TimeTo fields is not reliable, this rips out just the time
		# and sets all dates to be the same, then compares.
		# First it gets the hours and minutes with datepart, casts them to varchar so that they can be concatenated to 
		# look like '10:34', then casts this to datetime, which can be compared. Kludgy.
	
		$query = "
			SELECT	t.TimeFrom,
				t.TimeTo,
				t.PeriodNumber,
				t.PeriodNumberSeq
			FROM	TimetableDefinition t
			WHERE	(cast( cast( datepart( hh, t.TimeFrom) as varchar) + ':' + cast( datepart( mi, t.TimeFrom) as varchar) as datetime)
				<=
				cast( cast( datepart( hh, getdate()) as varchar) + ':' + cast( datepart( mi, getdate()) as varchar) as datetime))
				and
				(cast( cast( datepart( hh, getdate()) as varchar) + ':' + cast( datepart( mi, getdate()) as varchar) as datetime)
				<=
				cast( cast( datepart( hh, t.TimeTo) as varchar) + ':' + cast( datepart( mi, t.TimeTo) as varchar) as datetime))
				and
				t.FileSemester = '".$_SESSION['FileSemester']."'
				and
				t.FileYear = '".$_SESSION['FileYear']."'
				and
				TimetableGroup = '" . $campusString . "'";
		
		return $this->getDatabase()->getRow($query);
	}

	function getDayTimetable($synergy_id, $userID, $campus, $daynumber)
	{
		$timetabledef = $this->getTimetableDefinition($campus,$daynumber);

        if(!is_array($timetabledef)) {
           return false;
        }

		$campusModel = $this->campusMapper->findById($campus);  //TODO campus object should be passed
		if (!($campusModel instanceof \Schoolbox\Campus)) {
			return false;
		}
		$campusString = $campusModel->getExtTimetableCode();

		if($this->groupHandler->isStudent($userID))
		{
			$query ="SELECT 
				Timetable.Room, SubjectClasses.Description, Timetable.PeriodNumber, Timetable.ClassCode, StaffID
				FROM 
				SynergyOne.dbo.StudentClasses StudentClasses,
				SynergyOne.dbo.SubjectClasses SubjectClasses, 
				SynergyOne.dbo.Timetable Timetable
				WHERE 
				Timetable.ClassCampus = SubjectClasses.ClassCampus AND
				Timetable.ClassCode = SubjectClasses.ClassCode AND 
				Timetable.FileSemester = SubjectClasses.FileSemester AND 
				Timetable.FileType = SubjectClasses.FileType AND 
				Timetable.FileYear = SubjectClasses.FileYear AND
				StudentClasses.FileSemester = SubjectClasses.FileSemester AND
				StudentClasses.FileType = SubjectClasses.FileType AND
				StudentClasses.FileYear = SubjectClasses.FileYear AND 
				StudentClasses.ClassCampus = SubjectClasses.ClassCampus AND
				StudentClasses.ClassCode = SubjectClasses.ClassCode AND
				Timetable.FileSemester='".$_SESSION['FileSemester']."' AND 
				Timetable.FileYear='".$_SESSION['FileYear']."' AND
				(StudentClasses.ID='$synergy_id' AND
				Timetable.DayNumber = '$daynumber' AND
				Timetable.TimetableGroup = '$campusString' AND
				StudentClasses.StartDate <= getdate() AND
				(StudentClasses.StopDate >= getdate() OR StudentClasses.StopDate IS NULL))
				ORDER BY Timetable.PeriodNumber";
		}
		else
		{
			$query = "SELECT Timetable.Room, SubjectClasses.Description,
				Timetable.PeriodNumber, Timetable.ClassCode
				FROM SynergyOne.dbo.SubjectClasses SubjectClasses, 
				SynergyOne.dbo.Timetable Timetable
				WHERE Timetable.ClassCampus = SubjectClasses.ClassCampus AND
				Timetable.ClassCode = SubjectClasses.ClassCode AND 
				Timetable.FileSemester = SubjectClasses.FileSemester AND 
				Timetable.FileType = SubjectClasses.FileType AND 
				Timetable.FileYear = SubjectClasses.FileYear AND
				SubjectClasses.FileSemester='".$_SESSION['FileSemester']."' AND 
				SubjectClasses.FileYear='".$_SESSION['FileYear']."' AND
				Timetable.TimetableGroup = '$campusString' AND
				Timetable.StaffID='$synergy_id' AND 
				Timetable.DayNumber = '$daynumber'
				ORDER BY Timetable.PeriodNumber";
		}
		
		$result = $this->getDatabase()->query($query);
		//get first period
		if ($result !== FALSE && $result !== TRUE)
			$desc = $this->getDatabase()->getNextArray($result);
		 else
			$desc = "";

		//for each period
		$timetable = array();
		for ($x = 0; isset($timetabledef['desc'][$x]); $x++) 
		{
			$timetable[$x]['periodName'] = $timetabledef['desc'][$x];
			$timetable[$x]['periodStart'] = $timetabledef['start'][$x];
			$timetable[$x]['periodEnd'] = $timetabledef['end'][$x];
			$timetable[$x]['periodNumber'] = $timetabledef['number'][$x];
			$timetable[$x]['periodSequence'] = $timetabledef['sequence'][$x];
	
			if($timetabledef['sequence'][$x] != 2 && $desc['PeriodNumber'] == $timetable[$x]['periodNumber'])
			{
				$timetable[$x]['description'] = $desc['Description'];
				$timetable[$x]['code'] = $desc['ClassCode'];
				$timetable[$x]['room'] = $desc['Room'];
                $timetable[$x]['staffID'] = $desc['StaffID'];
				$desc = $this->getDatabase()->getNextArray();
				
				//bad data in synergetic.. why..?? 
				while($desc['PeriodNumber'] == $timetable[$x]['periodNumber'] && $desc != 0) 
						$desc = $this->getDatabase()->getNextArray();
			}
		}

        if(is_array($timetable)) {
		    usort($timetable, array($this, 'sortTimetable'));
        }

		return $timetable;
	
	}

	function getFullTimetable($synergy_id, $userID, $campus)
	{	
		$timetabledef = $this->getTimetableDefinition($campus, 1);

        if(!is_array($timetabledef['desc'])) {
            //try getting from full definition
            $timetabledef = $this->getTimetableDefinition($campus, 5);

            if(!is_array($timetabledef['desc'])){
                return false;
            }
        }

		$campusModel = $this->campusMapper->findById($campus);  //TODO campus object should be passed
		if (!($campusModel instanceof \Schoolbox\Campus)) {
			return false;
		}
		$campusString = $campusModel->getExtTimetableCode();

		if( $this->groupHandler->isStudent($userID))
		{
			$query ="SELECT
						Timetable.Room, SubjectClasses.Description, Timetable.DayNumber, Timetable.PeriodNumber,
						Timetable.ClassCode, Timetable.StaffID, vStaff.StaffTitle, vStaff.StaffPreferredName,
						vStaff.StaffSurname
					FROM
						SynergyOne.dbo.StudentClasses StudentClasses,
						SynergyOne.dbo.SubjectClasses SubjectClasses,
						SynergyOne.dbo.Timetable Timetable
					LEFT JOIN
						SynergyOne.dbo.vStaff vStaff ON Timetable.StaffID = vStaff.StaffID
					WHERE
						Timetable.ClassCampus = SubjectClasses.ClassCampus AND
						Timetable.ClassCode = SubjectClasses.ClassCode AND
						Timetable.FileSemester = SubjectClasses.FileSemester AND
						Timetable.FileType = SubjectClasses.FileType AND
						Timetable.FileYear = SubjectClasses.FileYear AND
						StudentClasses.FileSemester = SubjectClasses.FileSemester AND
						StudentClasses.FileType = SubjectClasses.FileType AND
						StudentClasses.FileYear = SubjectClasses.FileYear AND
						StudentClasses.ClassCampus = SubjectClasses.ClassCampus AND
						StudentClasses.ClassCode = SubjectClasses.ClassCode AND
						((Timetable.FileSemester=".$_SESSION['FileSemester'].") AND
						(Timetable.FileYear=".$_SESSION['FileYear'].") AND
						Timetable.TimetableGroup = '$campusString' AND
						(StudentClasses.ID='$synergy_id'))
						AND StudentClasses.StartDate <= getdate() AND (StudentClasses.StopDate >= getdate() OR StudentClasses.StopDate IS NULL)
						AND DayNumber > 0 AND DayNumber <= ".$timetabledef['dayCount']." AND PeriodNumber <= ".$timetabledef['periodCount']."
					ORDER BY Timetable.PeriodNumber, Timetable.DayNumber";
		}
		else
		{

            // if user has no timetable, $timetabledef['dayCount'] is NULL, not 0, which breaks SQL query
            if($timetabledef['dayCount'] == NULL) {
                $timetabledef['dayCount'] = 0;
            }

			$query = "SELECT
						Timetable.Room, SubjectClasses.Description, Timetable.DayNumber, Timetable.PeriodNumber,
						Timetable.ClassCode
					FROM
						SynergyOne.dbo.SubjectClasses SubjectClasses,
						SynergyOne.dbo.Timetable Timetable
					WHERE
						Timetable.ClassCampus = SubjectClasses.ClassCampus AND
						Timetable.ClassCode = SubjectClasses.ClassCode AND
						Timetable.FileSemester = SubjectClasses.FileSemester AND
						Timetable.FileType = SubjectClasses.FileType AND
						Timetable.FileYear = SubjectClasses.FileYear AND
						SubjectClasses.FileSemester='".$_SESSION['FileSemester']."' AND
						SubjectClasses.FileYear='".$_SESSION['FileYear']."' AND
						Timetable.TimetableGroup = '$campusString' AND
						Timetable.StaffID='$synergy_id' AND
						DayNumber > 0 AND DayNumber <= " . $timetabledef['dayCount'] . " AND
						PeriodNumber <= ".$timetabledef['periodCount']."
					ORDER BY Timetable.PeriodNumber, Timetable.DayNumber";
		}

		$dayCount = $timetabledef['dayCount'];

		$result = $this->getDatabase()->query($query);

		//get first period
		if ($result != 1)
			$desc = $this->getDatabase()->getNextArray();
		 else
			$desc = "";

		//for each period
		$timetable = array();
		for ($x = 0; isset($timetabledef['desc'][$x]); $x++) 
		{
			$timetable[$x]['periodName'] = $timetabledef['desc'][$x];
			$timetable[$x]['periodStart'] = $timetabledef['start'][$x];
			$timetable[$x]['periodEnd'] = $timetabledef['end'][$x];
			$timetable[$x]['periodNumber'] = $timetabledef['number'][$x];
			$timetable[$x]['periodSequence'] = $timetabledef['sequence'][$x];

			//for each day
			for ($y = 1; $y <= $dayCount; $y++)
			{	 
				if($timetabledef['sequence'][$x] != 2 && $desc['PeriodNumber'] == $timetable[$x]['periodNumber'] && $desc['DayNumber'] == $y)
				{
					$timetable[$x]['day'][$y]['periodOn'] = 1;
					$timetable[$x]['day'][$y]['description'] = $desc['Description'];
					$timetable[$x]['day'][$y]['code'] = $desc['ClassCode'];
					$timetable[$x]['day'][$y]['room'] = $desc['Room'];
                    $timetable[$x]['day'][$y]['staffID'] = $desc['StaffID'];
                    $timetable[$x]['day'][$y]['staffFirstname'] = $desc['StaffPreferredName'];
                    $timetable[$x]['day'][$y]['staffSurname'] = $desc['StaffSurname'];
                    $timetable[$x]['day'][$y]['staffTitle'] = $desc['StaffTitle'];
                    $desc = $this->getDatabase()->getNextArray();

					//bad data in synergetic.. why..?? 
					while($desc['DayNumber'] == $y && $desc['PeriodNumber'] == $timetable[$x]['periodNumber']) {
						$desc = $this->getDatabase()->getNextArray();
					}
				
				}
				else
				{	
					$timetable[$x]['day'][$y]['periodOn'] = 0;
				}
				
			}
		}
        if(is_array($timetable)) {
		    usort($timetable, array($this, 'sortTimetable'));
        }
        
		return $timetable;

	}

	function sortTimetable($a, $b) {
		$aTime = strtotime($a['periodStart']);
		$bTime = strtotime($b['periodStart']);
		if($aTime == $bTime)
			return 0;
		return ($aTime > $bTime) ? +1: -1;
	}
	
	function getDaySubjects($synergy_id, $daynum) {
		$query = "SELECT Timetable.Room, SubjectClasses.Description,
                       	 Timetable.PeriodNumber, Timetable.ClassCode, SubjectClasses.ClassCampus
                         FROM SynergyOne.dbo.SubjectClasses SubjectClasses, SynergyOne.dbo.Timetable Timetable
                         WHERE Timetable.ClassCampus = SubjectClasses.ClassCampus AND
                         Timetable.ClassCode = SubjectClasses.ClassCode AND Timetable.FileSemester =
                         SubjectClasses.FileSemester AND Timetable.FileType = SubjectClasses.FileType
                         AND Timetable.FileYear = SubjectClasses.FileYear AND
                         ((SubjectClasses.FileSemester=".$_SESSION['FileSemester'].") AND (SubjectClasses.FileYear='".$_SESSION['FileYear']."')
                         AND (Timetable.StaffID='$synergy_id')) AND Timetable.DayNumber = '$daynum'
			  	ORDER BY Timetable.PeriodNumber";
		
		return $this->getDatabase()->getAll($query);
	
	} 
	
	function getSubjectPeriod($code, $day) {	
		//give a subject code and day this function returns the period that it occurs in

		$query = "SELECT Timetable.PeriodNumber
			FROM SynergyOne.dbo.Timetable Timetable
			WHERE Timetable.FileSemester='".$_SESSION['FileSemester']."' AND Timetable.FileYear='".$_SESSION['FileYear']."' 
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

	/**
	 * @var MSSQL $database
	 */
	private function setDatabase ($database) {
		$this->database = $database;
	}

	/**
	 * @return MSSQL
	 */
	private function getDatabase () {
		return $this->database;
	}
}
