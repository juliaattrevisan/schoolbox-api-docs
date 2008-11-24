<?php

//showEvent object...gets all info required to populate template fields in event.htm

require_once("../../include/include.php");
require_once("../../include/html-time.php");
require_once("../../include/session.php");
require_once("../../include/permission.php");
require_once("sql/modsport.php");
require_once("sql/modopp.php");
require_once("sql/modvenue.php");
require_once("sql/modevent.php");
require_once("sql/modseason.php");

class initEvent
{
	function initEvent($flag = "", $id = "")
	{
                $this->flag = $flag;
                $this->id = $id;
                switch ($this->flag){
                        case 1:
				$this->initDisplay();
                                if ((isset($_POST['saveevent']) && $_POST['saveevent']))
                                {
                                        $date = getdate();
                                        $eventid = $_POST['eventid'];
                                        $tid = $_POST['team'];
                                        $oid = $_POST['opp'];
                                        $vid = $_POST['venue'];
                                        $day = $_POST['day'];
                                        $mth = $_POST['mth'];
                                        $hour = $_POST['hour'];
                                        $min = $_POST['min'];
                                        $period = $_POST['period'];
                                        $num = 0;
                                        foreach($eventid as $eid)
                                        {
                                                if ($period[$num] == "PM" && $hour[$num] < 12)
                                                        $hour[$num] += 12;
                                                $mySeason = new Season();
                                                $currSeason = $mySeason->getCurrent();
						if ($mth[$num] >= $currSeason['smonth'] && $mth[$num] > $currSeason['emonth']){
							//check if day is within the season dates for the first month
							if ($mth[$num] == $currSeason['smonth'] && $day[$num] < $currSeason['sday'])
								error(9, "Event date not between start and finish date of season1");
                                                	$year = $currSeason['syear'];
						}
						//mth part of finish year 
                                        	else if ($mth[$num] < $currSeason['smonth'] && $mth[$num] <= $currSeason['emonth']){
							//check if day is within the season dates for the last month
							if ($mth[$num] == $currSeason['emonth'] && $day[$num] > $currSeason['eday'])
								error(9, "Event date not between start and finish date of season2");
                                        	        $year = $currSeason['eyear'];
						}
						//season does not span a year!!! 
						else if($mth[$num] >= $currSeason['smonth'] && $mth[$num] <= $currSeason['emonth'])
						{
							//check if day is within the season dates
							if (($mth[$num] == $currSeason['emonth'] && $day[$num] > $currSeason['eday']) || ($mth[$num] == $currSeason['smonth'] && $day < $currSeason['sday']))
								error(9, "Event date not between start and finish date of season2");
                                        	        $year = $currSeason['syear'];
						}
						else 
							error(9, "Event date not between start and finish date of season3");

                                                $datetime = date("Y-m-d H:i:s" , mktime($hour[$num], $min[$num], "00" , $mth[$num], $day[$num]
, $year));
                                                $myEvent = new event($eid);
                                                $myEvent->modifyEvent($tid[$num], $vid[$num], $datetime, $oid[$num], "","", 1);
                                                $num++;
                                        }
				}
                                $this->displayEvent($this->id, $_REQUEST['type']);
				break;
			case 2:
				//add event for sport
				$this->initDisplay();
                                if ( isset($_POST['team'][0]) && $_POST['team'][0])
                                {
                                        $date = getdate();
                                        $tid = $_POST['team'];
                                        $oid = $_POST['opp'];
                                        $vid = $_POST['venue'];
                                        $day = $_POST['day'];
                                        $mth = $_POST['mth'];
                                        $hour = $_POST['hour'];
                                        $min = $_POST['min'];
                                        $period = $_POST['period'];
                                        if ($period == "PM" && $hour < 12)
                                                $hourmod = $hour + 12;
                                        else
                                                $hourmod = $hour;
                                        $mySeason = new Season();
                                        $currSeason = $mySeason->getCurrent();
					//mth part of start year
                                        if ($mth >= $currSeason['smonth'] && $mth > $currSeason['emonth']){
						//check if day is within the season dates for the first month
						if ($mth == $currSeason['smonth'] && $day < $currSeason['sday'])
							error(9, "Event date not between start and finish date of season1");
                                                $year = $currSeason['syear'];
					}
					//mth part of finish year 
                                        else if ($mth < $currSeason['smonth'] && $mth <= $currSeason['emonth']){
						//check if day is within the season dates for the last month
						if ($mth == $currSeason['emonth'] && $day > $currSeason['eday'])
							error(9, "Event date not between start and finish date of season2");
                                                $year = $currSeason['eyear'];
					}
					//season does not span a year!!! 
					else if($mth >= $currSeason['smonth'] && $mth <= $currSeason['emonth'])
					{
						//check if day is within the season dates
						if (($mth == $currSeason['emonth'] && $day > $currSeason['eday']) || ($mth == $currSeason['smonth'] && $day < $currSeason['sday']))
							error(9, "Event date not between start and finish date of season2");
                                                $year = $currSeason['syear'];
					}
					else 
                                         
					       error(9, "Event date not between start and finish date of season3");
						
                                        $datetime = date("Y-m-d H:i:s" , mktime($hourmod, $min, "00" , $mth, $day, $year));
                                        //insert into database
                                        $myEvent = new event();
                                        if ($myEvent->addEvent($tid[0], $vid[0], $datetime, $oid[0]))
                                        {
                                                $this->displayAddEvent($this->id, $_REQUEST['type'], $day, $mth, $hour, $min, $period, $oid[0], $vid
[0]);
                                        }
                                }
                                else
                                {
                                        if (isset($_POST['team']) && $_POST['team'])
                                                error(9, "No Team Selected");
                                        else
                                                $this->displayAddEvent($this->id, $_REQUEST['type']);
                                }
				break;
			case 3:
 				//delete event
                                $myEvent = new event($_GET['eid']);
                                $myEvent->deleteEvent();
				$this->initDisplay();
                                $this->displayEvent($this->id, $_REQUEST['type']);
				break;
			default:
				//no flag and therefore need to call sport.php
				//do redirect
		}
	}

	function initDisplay()
	{
		$this->t_body = new Template(TPL_directory);

		//setup file
		$this->t_body->set_file("tplBODY", "modules/sports/admin/event.htm");
	 	//output page
		include_once("../../include/page.php");
	
		//setup blocks
		$this->t_body->set_block("tplBODY", "modevents_body", "modevents_block");
		$this->t_body->set_block("modevents_body", "modevents_list", "modevents_listing");

		$this->t_body->set_block("tplBODY", "add_event", "addevent_block");
		$this->t_body->set_block("tplBODY", "menu", "menu_block");

		$this->t_body->set_var("DISPLAY", "");
		$this->t_body->set_var("NAV", "");
		$this->t_body->set_var("TITLE", "Sports Event Administration");
	}

	function displayAddEvent($sid, $type, $day="", $mth="", $hour="", $min="", $period="", $opp="", $ven="")
	{
		//displays form to add an event to database
		$mySport = new sport();
		$sport = $mySport->getSports($sid);
		$this->t_body->set_var("DISPLAY", "Add Event for ".$sport[0]['name']);
		$this->t_body->set_var("ID", $sid);
		$this->t_body->set_var("NAV", "<a href=\"event.php?id=".$sid."&flag=1&type=".$type."\"><b>Back</b></a>");
		
		$myTeam = new sport();
		$teams = $myTeam->getTeams($sid); 
		if (!$teams)
			error(39);
		$teamList = $this->createHTMLlist($teams, "", "section", "tid");
		$this->t_body->set_var("TEAM", $teamList);
		
		$myOpp = new opp();
		$opps = $myOpp->getOpp(); 
		$oppList = $this->createHTMLlist($opps, $opp, "opp", "oid");
		$this->t_body->set_var("OPP", $oppList);
		
		$myVenue = new venue();
		$venues = $myVenue->getVenues();
		$venList = $this->createHTMLlist($venues, $ven, "venue", "vid");
		$this->t_body->set_var("VENUE", $venList);

		if ($period == "PM") {
			$this->t_body->set_var("PM", "selected");
			$this->t_body->set_var("AM", "");
		}
		else {
			$this->t_body->set_var("AM", "selected");
			$this->t_body->set_var("PM", "");
		}		
		if (!$day) {
			$day = date("j", mktime(0,0,0,date("m"),(date("d")+(6-date("w"))),date("Y")));
			$mth = date("n", mktime(0,0,0,date("m"),(date("d")+(6-date("w"))),date("Y")));
			$min = "00";
			$hour = "8";
		}
		$this->t_body->set_var("DAY", createDateList($day, "day"));
		$this->t_body->set_var("MTH", createMonthList($mth, "mth"));	
		$this->t_body->set_var("HOUR", $hour);
		$this->t_body->set_var("MIN", $min);

		
	 	$this->t_body->set_var("modevents_block", "");	
		$this->t_body->parse("addevent_block", "add_event", true);
		$this->t_body->parse("menu_block", "menu", true);
		$this->t_body->pparse("finaloutput", "tplBODY"); 
	}

	function displayEvent($sid, $type)
	{
		$mySport = new sport();
		$sport = $mySport->getSports($sid);
		$this->t_body->set_var("DISPLAY", "Modify Events for ".$sport[0]['name']);
			$this->t_body->set_var("NAV", "<a href=\"sport.php?dest=4\"><b>Admin Menu</b></a>&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"event.php?id=".$sid."&flag=2&type=".$type."\"><b>Add Event</b></a>&nbsp;&nbsp;&nbsp;<a href=\"event.php?id=".$sid."&flag=1&type=0\"><b> Week / </b></a><a href=\"event.php?id=".$sid."&flag=1&type=1\"><b>Season</b></a>");
		$this->t_body->set_var("TYPE", $type);
		$this->t_body->set_var("ID", $sid);

		$myEvent = new event();
		$eventlist = $myEvent->getEventDetails($sid, $type);
		if ($eventlist) {		

			$myTeam = new sport();
			$teams = $myTeam->getTeams($sid); 
		
			$myOpp = new opp();
			$opps = $myOpp->getOpp(); 

			$myVenue = new venue();
			$venues = $myVenue->getVenues();

			foreach ($eventlist as $event) {
				$this->t_body->set_var("EVENTID", $event['eid']);

				$teamList = $this->createHTMLlist($teams, $event['tid'] , "section", "tid");
				$this->t_body->set_var("TEAM", $teamList);

				$oppList = $this->createHTMLlist($opps, $event['oid'], "opp", "oid");
				$this->t_body->set_var("OPP", $oppList);
			
				$venList = $this->createHTMLlist($venues, $event['vid'], "venue", "vid");
				$this->t_body->set_var("VENUE", $venList);
				
               		        $this->t_body->set_var("DAY", createDateList($event['day'], "day[]"));
                       		$this->t_body->set_var("MTH", createMonthList($event['month'], "mth[]"));
				
				$this->t_body->set_var("NP", "");		
				$this->t_body->set_var("WIN", "");		
				$this->t_body->set_var("LOSS", "");		
				$this->t_body->set_var("DRAW", "");		

				if ($event['period'] == "PM"){
					$this->t_body->set_var("PM", "selected");
					$this->t_body->set_var("AM", "");
				}
				else {
					$this->t_body->set_var("AM", "selected");
					$this->t_body->set_var("PM", "");
				}	
				$this->t_body->set_var("HOUR", $event['hour']);
				$this->t_body->set_var("MIN", $event['min']);
	
				$this->t_body->parse("modevents_listing", "modevents_list", true);
			}
		}	
		else
			$this->t_body->set_var("modevents_listing", "<font color=\"#FF0000\"><b>No events available</b></font>");
			
		$this->t_body->set_var("addevent_block", "");
		$this->t_body->set_var("menu_block", "");
		$this->t_body->parse("modevents_block", "modevents_body", true);
		$this->t_body->pparse("finaloutput", "tplBODY"); 

	}

	function createHTMLlist($array, $id, $namevalue, $idvalue)
	{
		$htmlList = "<option value=\"\">Not Selected</option>";
		foreach ($array as $arr)
		{
	 		if ($id == $arr[$idvalue]) 
				$htmlList .= "<option value=\"".$arr[$idvalue]."\" selected>".$arr[$namevalue]."</option>";
			else
				$htmlList .= "<option value=\"".$arr[$idvalue]."\">".$arr[$namevalue]."</option>";
		}		
		return $htmlList;
	}
	
}  
if (perm(1,3) > 0){
	$page = new initEvent($_REQUEST['flag'], $_REQUEST['id']);
}
else
	error(19);
	
?>
