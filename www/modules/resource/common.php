<?php
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
************************************************************/

// Includes common functions for creating drop downs and other information required

function checkModPerms(&$db, $rid, $uid)
{
	if($_SESSION['su'] == 1)
		return true;
	
	$query = "SELECT owner FROM resource WHERE id = '$rid'";
	$db->dbDoQuery($query); 
	$resource = $db->dbGetNextArray();
	
	if($resource['owner'] == $_SESSION['id'])
		return true;
	else
		return false;
}

function typeDropDown(&$db, $name, $selected = 0)
{
	$query = "SELECT id, name FROM luResourceType";
	$db->dbDoQuery($query);
	
	$typeDropDown = "<select name=\"$name\">";
	while($type = $db->dbGetNextArray())
	{
		if($selected == $type['id'])
			$typeDropDown .= "<option value=\"{$type['id']}\" selected>{$type['name']}</option>";
		else
			$typeDropDown .= "<option value=\"{$type['id']}\">{$type['name']}</option>";
	}
	$typeDropDown .= "</select>";
	return $typeDropDown;
}

function locDropDown(&$db, $name, $selected = 0)
{
	$query = "SELECT id, name FROM luResourceLocation";
	$db->dbDoQuery($query);
	
	$locDropDown = "<select name=\"$name\">";
	while($loc = $db->dbGetNextArray())
	{
		if($selected == $loc['id'])
			$locDropDown .= "<option value=\"{$loc['id']}\" selected>{$loc['name']}</option>";
		else
			$locDropDown .= "<option value=\"{$loc['id']}\">{$loc['name']}</option>";
	}
	$locDropDown .= "</select>";
	return $locDropDown;
}

function permDropDown($name, $selected = 0)
{
	$perms = unserialize( PERM_TABLE );

	$permDropDown = "<select name=\"$name\">";
	foreach($perms as $permValue => $permName)
	{
		if($selected == $permValue)
			$permDropDown .= "<option value=\"$permValue\" selected>$permName</option>";
		else
			$permDropDown .= "<option value=\"$permValue\">$permName</option>";
	}
	$permDropDown .= "</select>";
	return $permDropDown;
}

function roomDropDown(&$db, $name, $selected = 0)
{
	$query = "SELECT id, name FROM resource WHERE type = '". ROOM ."'";
	$db->dbDoQuery($query);
	
	$roomDropDown = "<select name=\"$name\">";
	while($room = $db->dbGetNextArray())
	{
		if($selected == $room['id'])
			$roomDropDown .= "<option value=\"{$room['id']}\" selected>{$room['name']}</option>";
		else
			$roomDropDown .= "<option value=\"{$room['id']}\">{$room['name']}</option>";
	}
	$roomDropDown .= "</select>";
	return $roomDropDown;
}

function checkDateNum($date)
{
	if($date < 1 || $date > 31)
		error(9, "Invalid date selected");
}

function checkMonthNum($date)
{
	if($date < 1 || $date > 12)
		error(9, "Invalid date selected");
}

function checkTime($hour, $minute)
{
	if ($hour < 0 || $hour > 12 || $minute < 0 || $minute >= 60 )
		error(9, "Invalid time selected");
}		

function createTime($hour, $minute, $period)
{
	if($period == 1) //am time period
		$time = $hour.":".$minute.":00";
	else if($period == 2) //pm period
		$time = ($hour+12).":".$minute.":00";
	else
		error(9, "Invalid time selected");
	
	return $time;
}

function get_monday ($week, $year="")
{
   $first_date = strtotime("1 January ".($year ? $year : date("Y")));
   $w_day = date("w", $first_date);
   $d_week = 0;
  
   switch($w_day)
   {
   case 1:    $monday = $first_date;
       break;
   case 2:
   case 3:
   case 4:    $d_week = 604800;
   default:$monday = strtotime("Monday", $first_date)-$d_week;
   };

       $plus_week = "+".($week-1)." week";

   return (strtotime($plus_week, $monday));
}; 

function createWeekList($selectedWeek, $name, $extras, $showcurrent = 1)
{
	$week = "<select name=\"$name\" class=\"formfield\" $extras>";
	
	$currentWeek = getCurrentWeek(mktime());
			
	for($i = 1; $i < 53; $i++)
	{

		if($i >= $currentWeek && $showcurrent)
		{
			$date = beginDateOfWeek(date("Y"), $i);	

			if($i == $selectedWeek)
        	                $week .= "<option value=\"$i\" selected>".date("jS of F", $date)."</option>";
        	        else
        	                $week .= "<option value=\"$i\">".date("jS of F", $date)."</option>";
		}
	}

	$week .= "</select>";
	
	return $week;
}

function beginDateOfWeek($year, $week_number) 
{ 
	// Numeric day of the week for the 1st of January, $year 
	$no = date("w", mktime(0,0,0,1,1,$year)); 

	// First day of first week in $year 
	$first_day = mktime(0,0,0,1,1,$year) - ($no-1) * 86400; 

	// Add $week weeks to first day (current week is not to be added) 
	$add_no_of_weeks = $week_number; 
	return strtotime("$add_no_of_weeks weeks", $first_day) - 86400; 
} 

function getCurrentWeek($timestamp)
{
	$currentDay = date("w", $timestamp);

	#weeks start on monday
	$currentWeek = date("W", $timestamp);
	#correct to start week on sunday
	if($currentDay == 0)
        	$currentWeek += 1;

	return $currentWeek;
}

?>