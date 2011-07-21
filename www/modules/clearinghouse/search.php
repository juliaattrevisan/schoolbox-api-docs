<?php

require_once('../../include/session.php');
require_once('../../include/include.php');
require_once('../../include/time.php');
require_once('event.php');

class EventSearch
{
	function EventSearch()
	{
		$this->db = new DeprecatedDatabase();
		
		$this->t_body = new Template(TPL_directory);
        $this->t_body->set_file('tplBODY', 'modules/clearinghouse/searchResult.htm');
		$this->t_body->set_var('TITLE', HEADING." - PD Clearing House");
	
  		include_once("../../include/page.php");
	
	}
	
	function performSearch($searchVars)
	{
		$eventObj = new Event;
		
		$fromTimestamp = '';
		$toTimestamp = '';
		
		if($searchVars['fromDate'])
		{
			list($fromDate, $fromMonth, $fromYear) = explode('/',$searchVars['fromDate'],3);
			$fromTimestamp = mktime(0,0,0,$fromMonth, $fromDate, $fromYear);
		}
		
		if($searchVars['toDate'])
		{
			list($toDate, $toMonth, $toYear) = explode('/',$searchVars['toDate'],3);
			$toTimestamp = mktime(0,0,-1,$toMonth, $toDate+1, $toYear);
		}
		
		$events = $eventObj->eventSearch($searchVars['keyword'], 
																$fromTimestamp,$toTimestamp, 
																$searchVars['minCost'],$searchVars['maxCost'],
																array('e.yearID'=>$searchVars['year'], 'e.formatID'=>$searchVars['format']),
																$searchVars['orderBy']
															);
		
		$this->t_body->set_block('tplBODY', 'event', 'event_block');
		
		foreach($events as $event)
		{
			$tplVar = array( 	'ID' => $event['id'],
										'NAME' => scrubTEXT($event['name']),
										'DATE' => date('l jS \of F Y g:ia', $event['start']),
										'DURATION' => timeToString($event['duration']),
										'COST' => scrubTEXT($event['cost']),
										'DESCRIPTION' => scrubTEXT($event['description']),
										'ORGANISER' => scrubTEXT($event['organiser']),
										'CATEGORY' => $event['category'],
										'FORMAT' => $event['format'],
										'YEAR' => $event['year'],
										'VENUE' => scrubTEXT($event['venue']),
									);
			$this->t_body->set_var($tplVar);
		
			$this->t_body->parse('event_block', 'event', true);
		}
		
		
	
	}
	
	function outputPage()
	{
		$this->t_body->pparse('finaloutput','tplBODY');
	}

	
}

$page = new EventSearch;
$page->performSearch($_GET);
$page->outputPage();

?>
