<?php
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
*	Functions used to get timetable information from 		*
*	synergy.												*
*   $Id: syn_timetable.php,v 1.11 2004/04/27 05:16:48 jleckie Exp $						    
*                                                           *
************************************************************/


function getTimetableDefinition($campus)
{
	$db = @mssql_connect(MSSQL_host,MSSQL_user,MSSQL_passwd);
	mssql_select_db(MSSQL_db, $db);
	
	$campusCode = unserialize(SYN_CAMPUS_Codes);
	
	$query = "SELECT TimetableDefinition.PeriodNumber, TimetableDefinition.PeriodNumberSeq, TimetableDefinition.TimeFrom,
				TimetableDefinition.TimeTo, TimetableDefinition.Description, TimetableDefinition.DayNumber
				FROM SynergyOne.dbo.TimetableDefinition TimetableDefinition
				WHERE TimetableDefinition.FileYear={$_SESSION['FileYear']} AND 
					TimetableDefinition.FileSemester={$_SESSION['FileSemester']} AND
					TimetableDefinition.TimetableGroup='{$campusCode[$campus]}' AND
					TimetableDefinition.DayNumber = 0
				ORDER BY TimetableDefinition.PeriodNumber, TimetableDefinition.PeriodNumberSeq
				";
	$result = mssql_query($query, $db);
	
	$periodCount = 0;
	
	while($period = mssql_fetch_array($result))
	{	
		preg_match('/Dec 30 1899 ([0-9]+):([0-9]+):[0-9]+:[0-9]+(AM|PM)/', $period['TimeTo'], $TimeToMatches);
		preg_match('/Dec 30 1899 ([0-9]+):([0-9]+):[0-9]+:[0-9]+(AM|PM)/', $period['TimeFrom'], $TimeFromMatches);
	
		$timetableDef['desc'][] = $period['Description'];
		$timetableDef['start'][] = (int)$TimeFromMatches[1].':'.$TimeFromMatches[2].strtolower($TimeFromMatches[3]);
		$timetableDef['end'][] = (int)$TimeToMatches[1].':'.$TimeToMatches[2].strtolower($TimeToMatches[3]);
		$timetableDef['number'][] = $period['PeriodNumber'];
		$timetableDef['sequence'][] = $period['PeriodNumberSeq'];
		$periodCount++;
	}
	
	$timetableDef['periodCount'] = $periodCount;
	
	$days = unserialize(TIMETABLE_day_count);
	$timetableDef['dayCount'] = $days[$campus];
	return $timetableDef;

}

//gets current day number
function getDayNumber($campus, $date = 0)
{
	//get day count
	$days = unserialize(TIMETABLE_day_count);

	if($days[$campus] == 5)
	{
		return date("w");
	}
	else if($days[$campus] == 7)
	{
		//date format yyyy-dd-mm 00:00:00
		if(!$date)
		{
			$date = mktime();
			$year = date("Y");
			$mssqldate = $year."-01-01 00:00:00";
		}
		else
		{
			$mssqldate = date('Y-d-m',$date).' 00:00:00';
		}
	
		$db = @mssql_connect(MSSQL_host,MSSQL_user,MSSQL_passwd);
		mssql_select_db(MSSQL_db, $db);
	
		$query = "SELECT CONVERT(CHAR(19),CalendarEvents.CalendarDate), CalendarEvents.CalendarType
		FROM SynergyOne.dbo.CalendarEvents CalendarEvents
		WHERE (CalendarEvents.CalendarType LIKE 'd%') AND CalendarEvents.CalendarDate >= '$mssqldate' ORDER BY 
		CalendarEvents.CalendarDate DESC";

		$result = mssql_query($query, $db);

		//get latest result only
		$day = @mssql_fetch_array($result);

		//cut out d from CalenderType string
		$startDay = substr($day[1], 1, 1);

		//turn mssql date into unix timestamp
		$startDate = strtotime($day[0]);
	
		//find number of days between both dates
		$difference = intval(($date - $startDate) / 86400);

		//find number of weekends between 2 dates
		$weekends = intval(($difference+date("w", $startDate)) / 7);

		$currentDay = ($startDay + $difference - 2 * $weekends - 1) % 7 + 1;

		//make correction for sunday (otherwise we travel back in time)
		if(date("w", $date) == 0)
			$currentDay = $currentDay % 7 + 1;

		return $currentDay;
	}
	else	//unknown day number 
		return 0;
}


//returns the period number for the current time
function getPeriodNumber($campus)
{
	$campusCodes = unserialize(SYN_CAMPUS_Codes);
	$campusString = $campusCodes[$campus];

	$db = @mssql_connect(MSSQL_host,MSSQL_user,MSSQL_passwd);
	mssql_select_db(MSSQL_db, $db);

		# Since the year information in the TimeFrom and TimeTo fields is not reliable, this rips out just the time
		# and sets all dates to be the same, then compares.
		# First it gets the hours and minutes with datepart, casts them to varchar so that they can be concatenated to 
		# look like '10:34', then casts this to datetime, which can be compared. Kludgy.
		#

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
			
	$result = mssql_query($query, $db);
	if ($result != 1)
		$period = @mssql_fetch_array($result);
	else
		return false;

	return $period;
}

function getDayTimetable($synergy_id, $group, $campus, $daynumber)
{

	if(isStudent($group))
	{
		$query ="SELECT Timetable.Room, SubjectClasses.Description,
			Timetable.PeriodNumber, Timetable.ClassCode
			FROM SynergyOne.dbo.StudentClasses StudentClasses,
			SynergyOne.dbo.SubjectClasses SubjectClasses, SynergyOne.dbo.Timetable
			Timetable
			WHERE Timetable.ClassCampus = SubjectClasses.ClassCampus AND
			Timetable.ClassCode = SubjectClasses.ClassCode AND Timetable.FileSemester =
			SubjectClasses.FileSemester AND Timetable.FileType = SubjectClasses.FileType
			AND Timetable.FileYear = SubjectClasses.FileYear AND
			StudentClasses.FileSemester = SubjectClasses.FileSemester AND
			StudentClasses.FileSemester = Timetable.FileSemester AND
			StudentClasses.FileType = SubjectClasses.FileType AND
			StudentClasses.FileType = Timetable.FileType AND StudentClasses.FileYear =
			SubjectClasses.FileYear AND StudentClasses.FileYear = Timetable.FileYear AND
			StudentClasses.ClassCampus = SubjectClasses.ClassCampus AND
			StudentClasses.ClassCode = SubjectClasses.ClassCode AND
			((Timetable.FileSemester=".$_SESSION['FileSemester'].") AND (Timetable.FileYear=".$_SESSION['FileYear'].") AND
			(StudentClasses.ID='$synergy_id')) AND Timetable.DayNumber = $daynumber
			ORDER BY Timetable.PeriodNumber";
	}
	else
	{
		if(SITE_location == 'mgs') {
		$query = "SELECT Timetable.Room, SubjectClasses.Description,
			Timetable.PeriodNumber, Timetable.ClassCode
			FROM SynergyOne.dbo.SubjectClasses SubjectClasses, 
			SynergyOne.dbo.Timetable Timetable
			WHERE Timetable.ClassCampus = SubjectClasses.ClassCampus AND
			Timetable.ClassCode = SubjectClasses.ClassCode AND 
			Timetable.FileSemester = SubjectClasses.FileSemester AND 
			Timetable.FileType = SubjectClasses.FileType AND 
			Timetable.FileYear = SubjectClasses.FileYear AND
			((SubjectClasses.FileSemester=".$_SESSION['FileSemester'].") AND (SubjectClasses.FileYear=".$_SESSION['FileYear'].") AND
			(Timetable.StaffID='$synergy_id')) AND Timetable.DayNumber = $daynumber
			ORDER BY Timetable.PeriodNumber";
		} else {
		$query = "SELECT Timetable.Room, SubjectClasses.Description,
			Timetable.PeriodNumber, Timetable.ClassCode
			FROM SynergyOne.dbo.SubjectClasses SubjectClasses, 
			SynergyOne.dbo.Timetable Timetable,
			SynergyOne.dbo.SubjectClassStaff SubjectClassStaff
			WHERE Timetable.ClassCampus = SubjectClasses.ClassCampus AND
			Timetable.ClassCode = SubjectClasses.ClassCode AND 
			Timetable.FileSemester = SubjectClasses.FileSemester AND 
			Timetable.FileType = SubjectClasses.FileType AND 
			Timetable.FileYear = SubjectClasses.FileYear AND
			Timetable.ClassCode = SubjectClassStaff.ClassCode AND 
			Timetable.FileSemester = SubjectClassStaff.FileSemester AND 
			Timetable.FileType = SubjectClassStaff.FileType AND 
			Timetable.FileYear = SubjectClassStaff.FileYear AND
			((SubjectClasses.FileSemester=".$_SESSION['FileSemester'].") AND (SubjectClasses.FileYear=".$_SESSION['FileYear'].") AND
			(SubjectClassStaff.StaffID='$synergy_id')) AND Timetable.DayNumber = $daynumber
			ORDER BY Timetable.PeriodNumber";
		}
	}
	
	
	//setup database
	$db = mssql_connect(MSSQL_host,MSSQL_user,MSSQL_passwd);
	$result = mssql_query($query, $db);

	$timetabledef = getTimetableDefinition($campus);
	
	$periodCount = $timetabledef['periodCount'];
	$dayCount = $timetabledef['dayCount'];
	
	//get first period
	if ($result != 1)
		$desc = mssql_fetch_array($result);
	 else
		$desc = "";	
		
	//for each period
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
			$desc = mssql_fetch_array($result);
			
			//bad data in synergetic.. why..?? 
			while($desc['PeriodNumber'] == $timetable[$x]['periodNumber'] && $desc != 0) 
					$desc = mssql_fetch_array($result); 
		}
	}

	return $timetable;

}

function getFullTimetable($synergy_id, $group, $campus)
{	
	if( isStudent($group))
	{
		$query ="SELECT Timetable.Room, SubjectClasses.Description, Timetable.DayNumber, 
			Timetable.PeriodNumber, Timetable.ClassCode 
			FROM SynergyOne.dbo.StudentClasses StudentClasses, 
			SynergyOne.dbo.SubjectClasses SubjectClasses, SynergyOne.dbo.Timetable 
			Timetable 
			WHERE Timetable.ClassCampus = SubjectClasses.ClassCampus AND 
			Timetable.ClassCode = SubjectClasses.ClassCode AND Timetable.FileSemester = 
			SubjectClasses.FileSemester AND Timetable.FileType = SubjectClasses.FileType 
			AND Timetable.FileYear = SubjectClasses.FileYear AND 
			StudentClasses.FileSemester = SubjectClasses.FileSemester AND 
			StudentClasses.FileSemester = Timetable.FileSemester AND 
			StudentClasses.FileType = SubjectClasses.FileType AND 
			StudentClasses.FileType = Timetable.FileType AND StudentClasses.FileYear = 
			SubjectClasses.FileYear AND StudentClasses.FileYear = Timetable.FileYear AND 
			StudentClasses.ClassCampus = SubjectClasses.ClassCampus AND 
			StudentClasses.ClassCode = SubjectClasses.ClassCode AND 
			((Timetable.FileSemester=".$_SESSION['FileSemester'].") AND (Timetable.FileYear=".$_SESSION['FileYear'].") AND 
			(StudentClasses.ID='$synergy_id'))
			ORDER BY Timetable.PeriodNumber, Timetable.DayNumber"; 
	}
	else
	{
		if(SITE_location == 'mgs') {
		$query = "SELECT Timetable.Room, SubjectClasses.Description, Timetable.DayNumber, 
			Timetable.PeriodNumber, Timetable.ClassCode
			FROM SynergyOne.dbo.SubjectClasses SubjectClasses, 
			SynergyOne.dbo.Timetable Timetable
			WHERE 
			Timetable.ClassCampus = SubjectClasses.ClassCampus AND 
			Timetable.ClassCode = SubjectClasses.ClassCode AND 
			Timetable.FileSemester = SubjectClasses.FileSemester AND 
			Timetable.FileType = SubjectClasses.FileType AND 
			Timetable.FileYear = SubjectClasses.FileYear AND
			((SubjectClasses.FileSemester=".$_SESSION['FileSemester'].") AND 
			(SubjectClasses.FileYear=".$_SESSION['FileYear'].") AND 
			(Timetable.StaffID='$synergy_id'))
			ORDER BY Timetable.PeriodNumber, Timetable.DayNumber";
		} else {
		$query = "SELECT Timetable.Room, SubjectClasses.Description, Timetable.DayNumber, 
			Timetable.PeriodNumber, Timetable.ClassCode
			FROM SynergyOne.dbo.SubjectClasses SubjectClasses, 
			SynergyOne.dbo.Timetable Timetable,
			SynergyOne.dbo.SubjectClassStaff SubjectClassStaff
			WHERE 
			Timetable.ClassCampus = SubjectClasses.ClassCampus AND 
			Timetable.ClassCode = SubjectClasses.ClassCode AND 
			Timetable.FileSemester = SubjectClasses.FileSemester AND 
			Timetable.FileType = SubjectClasses.FileType AND 
			Timetable.FileYear = SubjectClasses.FileYear AND 
			Timetable.ClassCode = SubjectClassStaff.ClassCode AND 
			Timetable.FileSemester = SubjectClassStaff.FileSemester AND 
			Timetable.FileType = SubjectClassStaff.FileType AND 
			Timetable.FileYear = SubjectClassStaff.FileYear AND
			((SubjectClasses.FileSemester=".$_SESSION['FileSemester'].") AND 
			(SubjectClasses.FileYear=".$_SESSION['FileYear'].") AND 
			(SubjectClassStaff.StaffID='$synergy_id'))
			ORDER BY Timetable.PeriodNumber, Timetable.DayNumber";
		}	
	}

	//setup database
	$db = mssql_connect(MSSQL_host,MSSQL_user,MSSQL_passwd);
	$result = mssql_query($query, $db);

	$timetabledef = getTimetableDefinition($campus);

	$periodCount = $timetabledef['periodCount'];
	$dayCount = $timetabledef['dayCount'];
	
	//get first period
	if ($result != 1)
		$desc = mssql_fetch_array($result);
	 else
		$desc = "";	
	
	
	//for each period
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
				$desc = mssql_fetch_array($result);
				
				//bad data in synergetic.. why..?? 
				while($desc['DayNumber'] == $y)
					$desc = mssql_fetch_array($result);
			
			}
			else
			{	
				$timetable[$x]['day'][$y]['periodOn'] = 0;
			}
			
		}
	}
	return $timetable;
}

?>
