<?php

require_once('../../include/session.php');
require_once('../../include/include.php');
require_once('../../include/time.php');
require_once('event.php');

class modEvent
{
	function modEvent()
	{
		$this->db = new Database();
		
		$this->t_body = new Template(TPL_directory);
        $this->t_body->set_file('tplBODY', 'modules/clearinghouse/modEvent.htm');
		$this->t_body->set_var('TITLE', HEADING." - PD Clearing House");
	
  		include_once("../../include/page.php");
	
	}
	
	function setVars($id)
	{
		
		$tplArray = array(	'ACTION' => 'Create',
										'NAME' => '',
										'ID' => '',
										'DATE' => '',
										'HOUR' => '',
										'MIN' => '',
										'PM' => '',
										'DURATION' => '',
										'COST' => '',
										'DESCRIPTION' => '',
										'KEYWORDS' => '',
										'ORGANISER' => '',
										'PRESENTER' => '',
										'VENUE' => '',
										'ADDRESS' => '',
										'URL' => '',
										);									
		
		$this->t_body->set_block('tplBODY', 'delete_button', 'delete_button_block');
		
		if(is_numeric($id))
		{
			$eventObj = new Event();	
			$event = $eventObj->getSingleEvent($id);
		
			$tplArray = array(	'ACTION' => 'Modify',
											'ID' => $event['id'],
											'NAME' => scrubTEXT($event['name']),
											'DATE' => date('j/n/Y', strtotime($event['start'])),
											'HOUR' => date('g', strtotime($event['start'])),
											'MIN' => date('i', strtotime($event['start'])),
											'PM' =>  date('a', strtotime($event['start'])) == 'pm' ? 'selected="selected"' : '',
											'DURATION' => $event['duration'] / 60,
											'COST' => scrubTEXT($event['cost']),
											'DESCRIPTION' => scrubTEXT($event['description']),
											'KEYWORDS' => scrubTEXT($event['keywords']),
											'ORGANISER' => scrubTEXT($event['organiser']),
											'PRESENTER' => scrubTEXT($event['presenter']),
											'VENUE' => scrubTEXT($event['venue']),
											'ADDRESS' => scrubTEXT($event['address']),
											'URL' => $event['url'],
										);
			
			$this->t_body->set_var($tplArray);
		
			$this->t_body->parse('delete_button_block', 'delete_button');
		
		}
		else
		{
			$this->t_body->set_var($tplArray);
			$this->t_body->set_var('delete_button_block','');
		}
		
		$this->outputDropDowns($event['categoryID'], $event['yearID'], $event['formatID']);
		
	}	
		
	function outputPage()
	{
		$this->t_body->pparse('finaloutput', 'tplBODY');
	}

	/*PRIVATE */
	
	function outputDropDowns($categorySelected, $yearSelected, $formatSelected)
	{
		$this->db->dbChangeDb('clearinghouse');
	
		$query = "SELECT * FROM luCategory";
		$categories = $this->db->getAll($query);
		$this->doDropDown('category', $categories, $categorySelected);
		
		$query = "SELECT * FROM luYear";
		$year = $this->db->getAll($query);
		$this->doDropDown('year', $year, $yearSelected);
		
		$query = "SELECT * FROM luFormat";
		$format = $this->db->getAll($query);
		$this->doDropDown('format', $format, $formatSelected);
		
	}
	
	function doDropDown($name, $array, $selected)
	{
		$this->t_body->set_block('tplBODY', $name.'_select', $name.'var');
		
		foreach($array as $value)
		{
			$this->t_body->set_var(strtoupper($name).'_ID', $value[$name.'ID']);
			$this->t_body->set_var(strtoupper($name).'_NAME', $value['name']);
			if($value[$name.'ID'] == $selected)
				$this->t_body->set_var(strtoupper($name).'_SELECT', 'selected="selected"');
			else
				$this->t_body->set_var(strtoupper($name).'_SELECT', '');
			$this->t_body->parse($name.'var', $name.'_select', true);
		}
	
	}
	
}	

if($_POST['delete'])
{
	//delete event
	$event = new Event;
	$event->deleteEvent($_POST['id']);
	localRedir('/modules/clearinghouse/index.php');
}
else if($_POST['name'])
{
	//check and reformat time information
	list($date,$month,$year) = explode('/',$_POST['date'],3); 
	if( !checkdate($month, $date, $year) )		
			error(9);
	checkTime($_POST['hour'], $_POST['min']);
	list($hours, $minutes, $seconds) = explode(':', createTime($_POST['hour'], $_POST['min'], $_POST['period']),3); 
	$startTime = mktime($hours, $minutes, $seconds, $month, $date, $year) ;
	$endTime = $startTime + ($_POST['duration'] * $_POST['durationPeriod'] * 60);
	
	$eventArray = array(	'name' => $_POST['name'],
								'start' => date('Y-m-d H:i:s', $startTime),
								'endTime' =>date('Y-m-d H:i:s', $endTime),
								'cost' => $_POST['cost'],
								'description' => $_POST['description'],
								'keywords' => $_POST['keywords'],
								'categoryID' => $_POST['category'],
								'yearID' => $_POST['year'],
								'formatID' => $_POST['format'],
								'organiser' =>$_POST['organiser'],
								'venue' => $_POST['venue'],
								'address' => $_POST['address'],
								'presenter' => $_POST['presenter'],
								'url' => $_POST['url'],
							);
								
	$eventObj = new Event;
	
	if($_POST['id'])
	{
		//modify event
		$id = $_POST['id'];
		$eventArray['id'] = $_POST['id'];
		$eventObj->modEvent($eventArray);
	}
	else
	{
		$eventArray['submitter'] = $_SESSION['id'];
		$id = $eventObj->addEvent($eventArray);
	}
	
	localRedir('/modules/clearinghouse/viewEvent.php?id='.$id);
}
else
{
	//output page
	$page = new modEvent;
	$page->setVars($_GET['id']);
	$page->outputPage();

}

?>
