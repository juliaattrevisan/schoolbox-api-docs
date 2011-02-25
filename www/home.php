<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

require_once 'include/stdfuncs.php';
require_once '../library/group.php';
$groupHandler = new Group;

if (strpos($_SERVER['REQUEST_URI'], 'home.php'))
	localRedir('/home');

//displays list of files either by search or during browsing.
$this->setFile(TEMPLATE_content, 'home.html');
$this->setTitle('Welcome');

//fetch layout for user
$query = "SELECT position, name, filename, function 
			FROM welcomeLayout LEFT JOIN welcomeComponent ON componentID = welcomeComponent.id
			WHERE gid = {$_SESSION['gid']}";
$result = $this->db->query($query);
while($component = $this->db->getNextArray($result))
{
	$j = 0;
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

		if($component['name'] == 'Timetable')
			$timetable = true;
	}
	else 
		$this->setVar('POS'.$component['position'], '');
}

//FIXME: Timetable is hardcoded in, we have to get rid of it if we dont include it in the config
if($timetable != true) {
	$this->setBlock(TEMPLATE_content, "timetable", "timetable_block");
	$this->setVar('timetable_block', '');
}

/* Parent greeting customisation
 */
$this->setBlock(TEMPLATE_content, 'greeting_name', 'greeting_name_block');

if ($groupHandler->isParent() && SITE_location == 'shore') {
    $this->setVar('greeting_name_block', '');
}
else {
    $this->parse('greeting_name_block', 'greeting_name');
}

?>
