<?php

$months = array(1 => "jan", "feb", "mar", "apr", "may", "jun", "jul", "aug", "sep", "oct", "nov", "decem");

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

function usernameToUserid($name, &$portaldb)
{

        //get users portalid
        $userquery = "SELECT id FROM user WHERE username LIKE '$name'";
        $portaldb->dbDoQuery($userquery); 
        if($portaldb->dbCountRows() != 1)
                return 0;

        $result = $portaldb->dbGetNextArray();
        return $result['id'];
}

function formatMonth($month)
{
        $month = strtolower($month);

        if($month == "dec")
                $month = "decem";

        return $month;
}

function wordListQuery($cid, &$db)
{
        //get word list for category
        $wordlistquery = "SELECT word FROM filter_word_list WHERE category = '$cid'"; 
        $db->dbDoQuery($wordlistquery);
        $list = "";
        while( $words = $db->dbGetNextArray() ) 
        {               
                //create word list for query
                $list .= " url LIKE '%".$words['word']."%' OR";
        }
        $list .= " 1 = 0";

	return $list;
}

function createWeekList($selectedWeek, $name, $extras)
{
	$week = "<select name=\"$name\" class=\"formfield\" $extras>";
	
	$currentWeek = getCurrentWeek(mktime());
			
	for($i = 1; $i < $currentWeek; $i++)
	{
		$date = beginDateOfWeek(date("Y"), $i);	

		if($i == $selectedWeek)
                        $week .= "<option value=\"$i\" selected>".date("jS \of F", $date)."</option>";
                else
                        $week .= "<option value=\"$i\">".date("jS \of F", $date)."</option>";
	}

	$week .= "</select>";
	
	return $week;
}

function getCampusList(&$db)
{
	$query = "SELECT * FROM luCampus";
	$db->dbDoQuery($query);

	while( $campus[] = $db->dbGetNextArray() ) ;

	return $campus;	
}

function createCampusList($campusList, $selectedCampus, $name, $extras)
{

	$campusHTML = "<select name=\"$name\" class=\"formfield\" $extras>";

	foreach($campusList as $campus)
	{
		if($campus['id'])
		{
			if($campus['id'] == $selectedCampus)
                	        $campusHTML .= "<option value=\"".$campus['id']."\" selected>".$campus['name']."</option>";
                	else
                	        $campusHTML .= "<option value=\"".$campus['id']."\">".$campus['name']."</option>";
		}
	}
	
	$campusHTML .= "</select>";

	return $campusHTML;
}
