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
//gets current day number
function getDayNumber()
{
	//get rotation style
	$rotation = unserialize(TIMETABLE_definition);

	if($rotation[$_SESSION['campus'][0]]['day'] == 5)
	{
		return date("w");
	}
	else if($rotation[$_SESSION['campus'][0]]['day'] == 7)
	{
		//date format yyyy-dd-mm 00:00:00
		$year = date("Y");
		$date = $year."-01-01 00:00:00";
	
		$db = @mssql_connect(MSSQL_host,MSSQL_user,MSSQL_passwd);
		mssql_select_db(MSSQL_db, $db);
	
		$query = "SELECT CONVERT(CHAR(19),CalendarEvents.CalendarDate), CalendarEvents.CalendarType
		FROM SynergyOne.dbo.CalendarEvents CalendarEvents
		WHERE (CalendarEvents.CalendarType LIKE 'd%') AND CalendarEvents.CalendarDate >= '$date' ORDER BY 
		CalendarEvents.CalendarDate DESC";

		$result = mssql_query($query, $db);

		//get latest result only
		$day = @mssql_fetch_array($result);

		//cut out d from CalenderType string
		$startDay = substr($day[1], 1, 1);

		//turn mssql date into unix timestamp
		$startDate = strtotime($day[0]);
	
		//find number of days between both dates
		$difference = intval((date("U") - $startDate) / 86400);

		//find number of weekends between 2 dates
		$weekends = intval(($difference+date("w", $startDate)) / 7);

		$currentDay = ($startDay + $difference - 2 * $weekends - 1) % 7 + 1;

		//make correction for sunday (otherwise we travel back in time)
		if(date("w") == 0)
			$currentDay = $currentDay % 7 + 1;

		return $currentDay;
	}
	else	//unknown day number 
		return 0;
}


//returns the period number for the current time
function getPeriodNumber()
{
	$campusCodes = unserialize(SYN_CAMPUS_codes);
	$campus = $campusCodes[$_SESSION['campus'][0]];

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
			TimetableGroup = '" . $campus . "'";

	$result = mssql_query($query, $db);
	if ($result != 1)
		$period = @mssql_fetch_array($result);
	else
		$period[2] = 0;

	return $period[2];
}

function getDayTimetableQuery($synergy_id, $year, $daynumber)
{

	if(isStudent($_SESSION['gid']))
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
			((Timetable.FileSemester=".$_SESSION['FileSemester'].") AND (Timetable.FileYear='$year') AND
			(StudentClasses.ID='$synergy_id')) AND Timetable.DayNumber = '$daynumber'
			ORDER BY Timetable.PeriodNumber";
	}
	else
	{
		$query = "SELECT Timetable.Room, SubjectClasses.Description,
			Timetable.PeriodNumber, Timetable.ClassCode
			FROM SynergyOne.dbo.SubjectClasses SubjectClasses, SynergyOne.dbo.Timetable
			Timetable
			WHERE Timetable.ClassCampus = SubjectClasses.ClassCampus AND
			Timetable.ClassCode = SubjectClasses.ClassCode AND Timetable.FileSemester =
			SubjectClasses.FileSemester AND Timetable.FileType = SubjectClasses.FileType
			AND Timetable.FileYear = SubjectClasses.FileYear AND
			((SubjectClasses.FileSemester=".$_SESSION['FileSemester'].") AND (SubjectClasses.FileYear='$year') AND
			(Timetable.StaffID='$synergy_id')) AND Timetable.DayNumber = '$daynumber'
			ORDER BY Timetable.PeriodNumber";
	}

	return $query;

}
?>
