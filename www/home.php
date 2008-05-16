<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

require_once 'include/stdfuncs.php';
if (strpos($_SERVER['REQUEST_URI'], 'home.php'))
	localRedir('/home');

//displays list of files either by search or during browsing.
$this->setFile(TEMPLATE_content, 'home.html');

$this->setVar("TITLE", HEADING." - Home");

$this->setBlock(TEMPLATE_content, "timetable", "timetable_block");	

if(date("A")=="AM")
	$greeting =  "Good Morning";
else if(date("G") < 17)
	$greeting =  "Good Afternoon";
else
	$greeting =  "Good Evening";

//set greeting
$this->setVar("GREETING", $greeting .' '. formatName($_SESSION));

//if user has timetable show it on the homepage
if($_SESSION['timetable'])
	$this->outputTimetable();
else
	$this->setVar("timetable_block", "");

//fetch layout for user
$query = "SELECT position, name, filename, function 
			FROM welcomeLayout LEFT JOIN welcomeComponent ON componentID = welcomeComponent.id
			WHERE gid = {$_SESSION['gid']}";
$result = $this->db->query($query);
while($component = $this->db->getNextArray($result))
{
	if($component['name'])
	{
		$this->setFile($component['name'], 'home/'.$component['filename']);
		
		$funcname = $component['function'];
		if(is_file($_SERVER['DOCUMENT_ROOT'].'/home/'.$funcname.'.php')) {
			include($_SERVER['DOCUMENT_ROOT'].'/home/'.$funcname.'.php');
		}
		else 
			$this->setVar('POS'.$component['position'], '');
	}
	else 
		$this->setVar('POS'.$component['position'], '');
}	

?>
