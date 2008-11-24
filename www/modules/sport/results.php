<?php

//sports results object...displays results according to sport chosen

require_once("../../include/include.php");
require_once("../../include/session.php");
require_once("../../include/permission.php");
require_once("../../include/html-time.php");
require_once("sql/modsport.php");
require_once("sql/modopp.php");
require_once("sql/modstaff.php");
require_once("sql/modvenue.php");
require_once("sql/modevent.php");
require_once("sql/modseason.php");

class initResults
{
	function initResults()
	{
                $this->flag = $_REQUEST['flag'];
                $this->id = $_REQUEST['id'];
                $this->search = $_REQUEST['search'];
		$this->redir = $_REQUEST['redir'];
                switch ($this->flag){
                        case 1:
   				//display results for sport and date chosen
                                if ($_POST['datech'] == 1){
                                        $day = $_POST['day'];
                                        $mth = $_POST['month'];
                                        $yr = $_POST['year'];
                                        $date = $yr."-".$mth."-".$day;
                                }
                                else //all dates
                                        $date = 0;
                                $sid = $_POST['sportselect'];
				$this->initDisplay();
                                $this->displayResults($sid, $date);
				break;
			case 2:
				//save results for each event
                                if ($_POST['saveevent'] && isset($_POST['saveevent'])){
                                        $win = $_POST['win'];
                                        $result = $_POST['results'];
                                        $eids = $_POST['eventid'];
                                        $num = 0;
                                        foreach ($eids as $eid){
                                                $myEvent = new Event($eid);
                                                $myEvent->modifyEvent("","","","",$win[$num], $result[$num], 0);
                                                $num++;
                                        }
                                }
				$this->initDisplay();
                                $this->displayResults($_POST['ksid'], $_POST['kdate']);
				break;
			default:
  				//display form for choosing dates and sports
				$this->initDisplay();
                                $this->displayResultsMenu();
				break;
		}
	}

	function initDisplay()
	{
		$this->t_body = new Template(TPL_directory);
		if ($this->redir){
			//setup file
 			$this->t_body->set_file("tplBODY", "modules/sports/sports_results.htm");
			//setup blocks
 			$this->t_body->set_block("tplBODY", "fixture", "fixture_sect");
                	$this->t_body->set_block("fixture", "sports_list", "sports_listing");      
                	$this->t_body->set_block("sports_list", "event_list", "event_listing");
                	$this->t_body->set_block("sports_list", "headers", "headers_block");
 			$this->t_body->set_var("TITLE", "Sports Results");
		}
		else {
			//setup file
			$this->t_body->set_file("tplBODY", "modules/sports/admin/results.htm");
			//setup blocks
 			//$this->t_body->set_block("tplBODY", "adminmenu", "adminmenu_block");
			$this->t_body->set_block("tplBODY", "results_menu", "results_form");
			$this->t_body->set_block("tplBODY", "modevents_body", "modevents");
			$this->t_body->set_block("modevents_body", "sports_list", "sports_listing");
			$this->t_body->set_block("sports_list", "headers", "headers_block");
			$this->t_body->set_block("sports_list", "event_list", "event_listing");
 			$this->t_body->set_var("TITLE", "Sports Results");
	 		//output page
			include_once("../../include/page.php");
		}
                $this->t_body->set_var("DISPLAY", "");
                $this->t_body->set_var("NAV", "");
	
	}

	function displayResults($sid, $specdate)
	{
		//display list of events according to choices
		if (!$this->redir){
			$this->t_body->set_var("KSID", $sid);
			$this->t_body->set_var("KDATE", $specdate);
		}
		$mySport = new sport();
		if ($sid != 0)
			$sports = $mySport->getSports($sid);	
		else
			$sports = $mySport->getSports();
		$noevents = 1;
		$isevents = 1;
		if ($sports){
			if ($this->redir){
 				$this->t_body->set_var("DISPLAY", "Results");
                        	$this->t_body->set_var("NAV", "");
			}
			else{
 				$this->t_body->set_var("DISPLAY", "Modify Results");
                        	$this->t_body->set_var("NAV", "<a href=\"sport.php?dest=4\"><b>Admin Menu</b></a>");
			}
			foreach($sports as $sport){
	
				$prevdate = NULL;
				$myEvent = new event();
				if ($this->redir) {
 					$mySeason = new Season;  
                                	$cs = $mySeason->getCurrent();      
                                	$this->t_body->set_var("DATE", $cs['sday']."/".$cs['smonth']."/".$cs['syear']." - ".$cs['eday']."/".$cs['emonth']."/".$cs['eyear']);
				}
	 			//get events where type = 2 ie.only get past events 
				if (!$specdate) {
					$eventlist = $myEvent->getEventDetails($sport['sid'], 1);
				}
				//else specify date
				else {
					$eventlist = $myEvent->getEventDetails($sport['sid'], 3, $specdate);
				}
               			if ($eventlist)
               			{
                			foreach ($eventlist as $event)
                       			{
						$noevents = 0;
		 				$date = $event['day']."/".$event['month']; 
  						if ($date != $prevdate && $prevdate != NULL) {
                                                	$this->t_body->parse("sports_listing", "sports_list", true);
                                        	}
       						$this->t_body->set_var("SPORTNAME", $sport['name']."&nbsp;$date");
                       				$this->t_body->set_var("TEAM", $event['section']); 
                       				$this->t_body->set_var("OPP", $event['opp']); 
                                                if ($this->redir){
							$this->t_body->set_var("VENUE", $event['venue']);
							if ($event['win'] == 3)
								$this->t_body->set_var("WIN", "DRAW");
							else if ($event['win'] == 1)
								$this->t_body->set_var("WIN", "WIN");
							else if ($event['win'] == 2)
								$this->t_body->set_var("WIN", "LOSS");
							else 
								$this->t_body->set_var("WIN", "N/P");
						
						}
                                               	else{
							$this->t_body->set_var("EVENTID", $event['eid']);
							//if more options other than win, loss, draw and np are added then number needs
							//to be incremented
							for ($i = 0; $i < 4; $i++){
								if ($event['win'] == $i)
                               						$this->t_body->set_var("WIN".$i, "selected");
                              					else 
                                       					$this->t_body->set_var("WIN".$i, "");
							}
						}
                                		$this->t_body->set_var("RESULTS", $event['result']);

 						if (!$prevdate) {
                                                	//first time through
                                                	$this->t_body->parse("event_listing", "event_list", false);
                                                	$this->t_body->parse("headers_block", "headers", false);
                                        	}
                                        	else if ($date == $prevdate) {
                                               		 //continuation of list for same date
                                               		 $this->t_body->parse("event_listing", "event_list", true);
                                        	}
                                        	else {
                                                	//different date therefore new section list
                                                	$this->t_body->parse("event_listing", "event_list", false);
                                        	}
                                        	$prevdate = $date;
					}
				}
				else {
					$isevents = 0;
				}
				if ($isevents)
					$this->t_body->parse("sports_listing", "sports_list", true);
					
				$isevents = 1;
			}
		}
		else
 			$this->t_body->set_var("sports_listing", "<font color=\"#FF0000\"><br>No listing available, current season may not be set<br><br>Please speak to sports administrators</font>");
		if ($noevents)
			$this->t_body->set_var("sports_listing", "<font color=\"#FF0000\"><b>No events found</b></font>");

                $this->t_body->set_var("NAV", "");
		$this->t_body->set_var("results_form", "");
		$this->t_body->parse("headers_block", "headers", true);
		if ($this->redir)
 			$this->t_body->parse("fixture_sect", "fixture", true);
		else {
			//$this->t_body->parse("adminmenu_block", "adminmenu", true);
			$this->t_body->parse("modevents", "modevents_body", true);
		}
		$this->t_body->pparse("finaloutput", "tplBODY");
	}
	
	function displayResultsMenu()
	{
 		$this->t_body->set_var("DISPLAY", "Results Search");
                $this->t_body->set_var("NAV", "");
                $this->t_body->set_var("ID", $sid);
                $this->t_body->set_var("REDIR", $this->search);
		$date = getdate();
		$this->t_body->set_var("DAY", createDateList($date['mday'], "day"));	
		$this->t_body->set_var("MTH", createMonthList($date['mon'], "month"));	
		$this->t_body->set_var("YEAR", createYearList($date['year'], "year"));	

		$select = "<select name=\"sportselect\" class=\"formfield\">";
		$mySport = new sport();	
		$sports = $mySport->getSports();	
		$select.="<option value=\"0\">All Sports</option>"; 
		foreach ($sports as $sport)
		{	 
			$select.="<option value=\"".$sport['sid']."\">".$sport['name']."</option>"; 
		}	
		$select .= "</select>";
		$this->t_body->set_var("SPORTSELECT", $select);
		$this->t_body->set_var("modevents", "");
		
		/*if ($this->search)
			$this->t_body->set_var("adminmenu_block", "");
		else
			$this->t_body->parse("adminmenu_block", "adminmenu", true);
		*/
		$this->t_body->parse("results_form", "results_menu", true);
		$this->t_body->pparse("finaloutput", "tplBODY");
	}
	
	function array_column_sort($array, $column)
	{
		foreach ($array as $row)
		$sortarr[] = $row[$column];
		array_multisort($sortarr, $array);
		return $array;
	}
}  
$page = new initResults();
?>
