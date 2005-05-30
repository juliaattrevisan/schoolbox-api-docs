<?php
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
*	This file has generic functions for creating time/date	*
*	drop down boxes for use in forms						*
*   $Id: html-time.php,v 1.5 2004/04/27 05:16:48 jleckie Exp $						    
*                                                           *
************************************************************/

function createMonthList($currentMonth, $name)
{
	$month = "<select name=\"$name\" class=\"formfield\">";
	for($i=1;$i<=12;$i++)
	{
		if($currentMonth == $i)
			$month .= "<option value=\"$i\" selected>".date("F", mktime(0,0,0,$i,1,0))."</option>";
		else 
			$month .= "<option value=\"$i\">".date("F", mktime(0,0,0,$i,1,0))."</option>";
	}
	$month .= "</select>";
	return $month;
}
	
function createYearList($theYear, $name)
{
	$year = "<select name=\"$name\" class=\"formfield\">";
	$date = getDate();
	$currentYear = $date['year'];
 	$initialYear = "2003";	
	for ($i = $initialYear ; $i <= $currentYear+3 ; $i++)
	{
		if ($theYear == $i)
			$year .= "<option value=\"$i\" selected>$i</option>";
		else
			$year .= "<option value=\"$i\">$i</option>";
	}
	$year .= "</select>";
	return $year;
}

function createDateList($currentDate, $name)
{
	$date = "<select name =\"$name\" class=\"formfield\">";
	for($i=1;$i<=31;$i++)
	{
		if($currentDate == $i)
			$date .= "<option value=\"$i\" selected>$i</option>";
		else
			$date .= "<option value=\"$i\">$i</option>";
	}
	$date .= "</select>";

	return $date;
}

function createTimeList($currentHour, $name)
{
	$time = "<select name=\"$name\" class=\"formfield\">";
	for($i=8; $i<=20; $i++)
	{
		if($i == $currentHour)
			$time .= "<option value=\"$i:00\" selected>$i:00</option>";
        	else
			$time .= "<option value=\"$i:00\">$i:00</option>";
	}
	$time .= "</select>";
	return $time;
}

?>
