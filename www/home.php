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
$this->setTitle('Welcome');

//setup required includes
$this->setCSS('/css/'.SITE_location.'-home.css');

if(date("A")=="AM")
	$greeting =  "Good Morning";
else if(date("G") < 17)
	$greeting =  "Good Afternoon";
else
	$greeting =  "Good Evening";

//set greeting
$this->setVar("GREETING", $greeting .' '. formatName($_SESSION));

//fetch layout for user
$query = "SELECT position, name, filename, function 
			FROM welcomeLayout LEFT JOIN welcomeComponent ON componentID = welcomeComponent.id
			WHERE gid = {$_SESSION['gid']}";
$result = $this->db->query($query);
while($component = $this->db->getNextArray($result))
{
	if($component['name'])
	{
		if($component['filename'])
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
