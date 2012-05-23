<?php

include_once("../../include/include.php");
include_once("../../include/session.php");
include_once("../../include/permission.php");
include_once("sql/modevent.php");
include_once("sql/modsport.php");

if (perm(1,3) > 0){

//type = weekly
$type = 0;
$mySport = new sport();
//sport = all sports therefore no sport id as parameter
$sportslist = $mySport->getSports();
$data = "";
$header .= "Sport\tTeam\tOpposition\tVenue\tMelRef\tDate\tTime\tStaff1\tStaff2\t";
foreach ($sportslist as $sport)
{
	$myEvent = new event();
	$eventlist = $myEvent->getEventDetails($sport['sid'],$type);


	if ($eventlist){
 	  foreach ($eventlist as $event){
  		$line = '';
		$date = $event['day']."/".$event['month']."/".$event['year'];
		$time = $event['hour'].":".$event['min']." ".$event['period'];
		$line = $sport['name']."\t".$event['section']."\t".$event['opp']."\t".$event['venue']."\t".$event['melref']."\t".$date."\t".$time."\t".$event['fname1']." ".$event['lname1']."\t".$event['fname2']." ".$event['lname2']."\t";
  		$data .= trim($line)."\n";
	  }
	}
}

# this line is needed because returns embedded in the data have "\r"
# and this looks like a "box character" in Excel
$data = str_replace("\r", "", $data);

# Search came up empty.
# Otherwise only the column name headers will be output to Excel.
if ($data == "") {
  	$data = "\nno matching records found\n";
}

# This line will stream the file to the user rather than spray it across the screen
header("Content-type: application/octet-stream");

header("Content-Disposition: attachment; filename=sports_fixture.xls");

echo $header."\n".$data;
}
else
	error(19);
?>
