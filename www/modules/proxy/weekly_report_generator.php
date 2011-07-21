<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

//create weekly statistics from information in the url proxy log table

//start by working out current date and week we will be processing
//also remember to check if the week spans 2 months.

require_once($_SERVER['DOCUMENT_ROOT']."/include/include.php");
require_once("common.php");

//only localhost can access this file
if($_SERVER['server_addr'] != $_SERVER['remote_addr'])
	error(0); 


if($_GET['week'] == 0)
	$current = mktime() - 604800;
else
        $current = beginDateOfWeek(date("Y"), $_GET['week']) + 10000;



//$current = mktime();

$currentDate = date("j", $current);
$currentMonth = date("n", $current);
$currentYear = date("Y", $current);
$currentDay = date("w", $current); 

$currentWeek = getCurrentWeek($current);

$timestampStartOfWeek = $current - $currentDay * 86400;
$timestampEndOfWeek = $current + (6 * 86400 - $currentDay * 86400);

$startOfWeekDate = date("j", $timestampStartOfWeek);
$startOfWeekMonth = date("n", $timestampStartOfWeek);
$startOfWeekYear = date("Y", $timestampStartOfWeek);
$startOfWeekMonthName = formatMonth(date("M", $timestampStartOfWeek));
$timestampStartOfWeek = mktime(0,0,0,$startOfWeekMonth, $startOfWeekDate, $startOfWeekYear);


$endOfWeekDate = date("j", $timestampEndOfWeek);
$endOfWeekMonth = date("n", $timestampEndOfWeek);
$endOfWeekYear = date("Y", $timestampEndOfWeek);
$endOfWeekMonthName = formatMonth(date("M", $timestampEndOfWeek));
$timestampEndOfWeek = mktime(23,59,59,$endOfWeekMonth, $endOfWeekDate, $endOfWeekYear);


if($startOfWeekMonth != $endOfWeekMonth)
{
	$tableName = $startOfWeekMonthName."_".$endOfWeekMonthName;
}
else
{
	$tableName = $startOfWeekMonthName;
}

print "selecting data from $tablename<br>";
print "week starts $startOfWeekDate - $startOfWeekMonth<br>";
print "week end $endOfWeekDate - $endOfWeekMonth<br>";

$query = "SELECT user, sum(size) as totalsize FROM ".$tableName." 
	WHERE time > FROM_UNIXTIME('".$timestampStartOfWeek."')
	AND time < FROM_UNIXTIME('".$timestampEndOfWeek."')
	GROUP BY user"; 




//setup db connections
$proxydb = new DeprecatedDatabase("proxy", 1);
$proxydb->dbConnect();

//execute the query
$handle = $proxydb->query($query);
	
while( $userusage = $proxydb->getNextArray($handle) )
{
	//get users portalid

	$proxydb->query("USE intranet");
	$userid = usernameToUserid($userusage['user'], &$proxydb);
	$proxydb->query("USE proxy");

	if($userid == 0)
	{
		print "error finding user: ".$userusage['user']."<br>";
	}
	else
	{

		$report = "INSERT INTO usage_report_week (year, week, user, datausage) VALUES
		('$startOfWeekYear', '$currentWeek', '$userid', '".$userusage['totalsize']."')";

		$proxydb->query($report); 
	}
}

//now create word match report

//get category list
$categoryquery = "SELECT id FROM filter_category";
$cathandle = $proxydb->query($categoryquery);

while( $cat =  $proxydb->getNextArray($cathandle) )
{
	$list = wordListQuery($cat['id'], &$proxydb);

	$hitquery = "SELECT user, COUNT(*) as matches, SUM(size) as datasize FROM ".$tableName." 
			WHERE time > FROM_UNIXTIME('".$timestampStartOfWeek."')
			AND time < FROM_UNIXTIME('".$timestampEndOfWeek."') 
			AND ( $list ) GROUP BY user";

	$matchhandle = $proxydb->query($hitquery);

	while($hits  = $proxydb->getNextArray($matchhandle) )
	{
	
		$proxydb->query("USE intranetdev");
		$userid = usernameToUserid($hits['user'], &$proxydb);
		$proxydb->query("USE proxy");
		
		if($userid == 0)
			print "error finding user: ".$userusage['user']."<br>";
		else
		{

			$report = "INSERT INTO filter_report_week (year, week, user, category, count, datausage) VALUES
			('$startOfWeekYear', '$currentWeek', '$userid', '".$cat['id']."', '".$hits['matches']."', '".$hits['datasize']."')";

			$proxydb->query($report);
		}
	}
}

?>
