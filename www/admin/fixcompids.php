<?php

// this script does not
// - add a new invisible column to homepage_comp
// - set any components to invisible.
// - add the DUTIES component


require_once '../include/include.php';

// sitedev
$tempID = array ("CALENDAR" 		=> 1000,
				 "SMALL CALENDAR" 	=> 1001,
				 "IMAGE GALLERY" 	=> 1002,
				 "RANDOM IMAGE" 	=> 1003,
				 "FILE DROP BOX" 	=> 1004,
				 "RSS FEED" 		=> 1005,
				 "SMALL RSS FEED" 	=> 1006,
				 "DUE WORK" 		=> 1007,
				 "SMALL DUE WORK" 	=> 1008,
				 "CLASS TEACHERS" 	=> 1009,
				 "CAMPUS CALENDAR" 	=> 1010,
				 "DUTIES" 			=> 1011,
				 );



$db = new dbManager(SQL_db, 1);
$query = "SELECT UPPER(name) as name, id FROM homepage_comp WHERE id > 20 ORDER BY id";

$currentIDs = $db->dbGetAllRows($query);
if (is_array($currentIDs)) {
	foreach ($currentIDs as $current) {
	if (!is_int($tempID[$current['name']])) {
		print "<b>Error: There is no tempID set for '" . $current['name'] . "' </b><br />\n";
		continue;
	}
	print "Updating " . $current['name'] . " component to temp ID (" .$tempID[$current['name']]  . ").<br />\n";
	$query = "UPDATE homepage_comp SET id = " . $tempID[$current['name']] . " WHERE id = " . $current['id'] ;
	//print $query . "\n";
	$db->dbDoQuery($query);

	$query = "UPDATE homepage_comp_location SET component = " . $tempID[$current['name']] . " WHERE component = " . $current['id'];
	$db->dbDoQuery($query);
	//print $query . "\n";	
	}
}

$query = "SELECT id, name FROM homepage_comp WHERE id > 20 AND id < 1000";
$db->dbDoQuery($query);
if ($db->dbCountRows() > 0) {
	die("Error: There are left over components that weren't updated.");
}

$query = "SELECT UPPER(name) as name, id FROM homepage_comp WHERE id > 20 ORDER BY id";
$currentIDs = $db->dbGetAllRows($query);
$newID = 20;
if (is_array($currentIDs)) {
	foreach ($currentIDs as $current) {
	$newID++;

	print "Updating " . $current['name'] . " component to new ID (" . $newID . ").<br />\n";
	$query = "UPDATE homepage_comp SET id = " . $newID  . " WHERE id = " . $tempID[$current['name']] ;
	$db->dbDoQuery($query);
	
	$query = "UPDATE homepage_comp_location SET component = " . $newID . " WHERE component = " . $tempID[$current['name']] ;
	$db->dbDoQuery($query);
		
	}
}

// i know this is going to be 31 everytime, but it makes me feel safer
$query = "SELECT MAX(id) as id FROM homepage_comp";
$maxID = $db->dbGetSingleRow($query);

$query = "ALTER TABLE homepage_comp AUTO_INCREMENT = " . ($maxID['id'] + 1);
$db->dbDoQuery($query);




?>