<?php

require_once('../../include/session.php');
require_once('../../include/include.php');
require_once('../../include/time.php');
require_once('event.php');

class viewEvent
{
	function viewEvent()
	{
		$this->db = new DeprecatedDatabase();
		
		$this->t_body = new Template(TPL_directory);
        $this->t_body->set_file('tplBODY', 'modules/clearinghouse/viewEvent.htm');
		$this->t_body->set_var('TITLE', HEADING." - PD Clearing House");
	
  		include_once("../../include/page.php");
	
	}

	function outputEvent($id)
	{
		$eventObj = new Event();	
		$event = $eventObj->getSingleEvent($id);
		
		if(is_array($event))
		{
			$tplArray = array(	'ID' => $event['id'],
											'ACTION' => 'Modify',
											'NAME' => scrubTEXT($event['name']),
											'DATE' => date('l jS \of F Y g:ia', $event['start']),
											'DURATION' => timeToString($event['duration']),
											'COST' => scrubTEXT('$'.$event['cost']),
											'DESCRIPTION' => scrubTEXT($event['description']),
											'ORGANISER' => scrubTEXT($event['organiser']),
											'PRESENTER' => scrubTEXT($event['presenter']),
											'CATEGORY' => $event['category'],
											'FORMAT' => $event['format'],
											'YEAR' => $event['year'],
											'VENUE' => scrubTEXT($event['venue']),
											'ADDRESS' => scrubTEXT($event['address']),
											'URL' => $event['url'],
										);
			
			$this->t_body->set_var($tplArray);
		}
		else
		{
			error(0);
		}
	}
	
	function outputPage()
	{
		$this->t_body->pparse('finaloutput','tplBODY');
	}
	

}

if(is_numeric($_GET['id']))
{
	$page = new viewEvent;
	$page->outputEvent($_GET['id']);
	$page->outputPage();
}
else
	error(9, 'No event ID supplied');
?>
