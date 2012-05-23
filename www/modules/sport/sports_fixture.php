<?php

//sports fixture object...displays fixture according to users choice of options 

require_once("../../include/session.php");
require_once("../../include/include.php");
require_once("../../include/html-time.php");
require_once("sql/modsport.php");
require_once("sql/modopp.php");
require_once("sql/modstaff.php");
require_once("sql/modvenue.php");
require_once("sql/modevent.php");
require_once("sql/modseason.php");


class sports_fixture
{
	function sports_fixture($print = "")
	{
	  if (!$print){
		$this->t_body = new Template(TPL_directory);
		//setup file
		$this->t_body->set_file("tplBODY", "modules/sports/sports_fixture.htm");
	 	//output page
	
		//setup blocks
		$this->t_body->set_block("tplBODY", "fixture", "fixture_sect");
		$this->t_body->set_block("fixture", "sport_list", "sport_listing");
		$this->t_body->set_block("sport_list", "event_list", "event_listing");
		$this->t_body->set_block("sport_list", "menu_header", "menu_heading");
		$this->t_body->set_block("menu_header", "grimtime", "grimtime_sect");
		$this->t_body->set_block("menu_header", "normtime", "normtime_sect");

 		$this->t_body->set_var("TITLE", "Sports Fixture");
                $this->t_body->set_var("DISPLAY", "");
                $this->t_body->set_var("NAV", "");
	  }
	
	}
	//type = 0: weekly , type = 1: season
	function displayFixture($type, $sid = "", $print = "")
	{
		//display list of events according to choices
		$isevents = 0;
		$noevents = 1;
		$mySport = new sport();
		$sports = $mySport->getSports($sid);	
		if ($sports){
		foreach ($sports as $sport)
		{
			$myEvent = new event();
			$eventlist = $myEvent->getEventDetails($sport['sid'], $type);
               		if ($eventlist)
               		{
				$noevents = 0;
				$isevents = 1;
				if (!$print){
					$prevdate = NULL;
					if ($type == 0) {
 						$fixlen = "Saturday";
						$this->t_body->set_var("DATE",date("d.m.Y",mktime(0,0,0,date("m"),(date("d")+(6-date("w"))),date("Y"))));
					}
					else {
						$fixlen = "Season"; 	
						$mySeason = new season(); 
						$cs = $mySeason->getCurrent();
						$this->t_body->set_var("DATE", $cs['sday']."/".$cs['smonth']."/".$cs['syear']." - ".$cs['eday']."/".$cs['emonth']."/".$cs['eyear']);
 					}
				}
				$menu = 1;
                       		foreach ($eventlist as $event)
                       		{
					if ($print){
					 	$event['opp'] ? $popp = $event['opp'] : $popp = "&nbsp;";
						$event['venue'] ? $pvenue = $event['venue'] : $pvenue = "&nbsp;";
						$event['melref'] ? $pmelref = $event['melref'] : $pmelref = "&nbsp;";
						$pname1 = $event['fname1'].$event['lname1'];
						$pname1 ? "" : $pname1 = "&nbsp;";
						$pname2 = $event['fname2'].$event['lname2'];
						$pname2 ? "" : $pname2 = "&nbsp;";
						$event['time'] ? $ptime = $event['time'] : $ptime = "&nbsp;";
						$filestring.="<tr><td>".$sport['name']."</td><td>".$event['section']."</td><td>".$popp."</td><td>".$pvenue."</td><td>".$pmelref."</td><td>".$pname1."</td><td>".$pname2."</td><td>".$ptime."</td></tr>"; 
					}
					else {
				 		$date = $event['day']."/".$event['month']."/".$event['year']; 
						if ($date != $prevdate && $prevdate != NULL) {
							if ($isevents)
                       						$this->t_body->parse("sport_listing", "sport_list", true);
						}

       						$this->t_body->set_var("SPORTNAME", $sport['name']." ".$date);
                               			$this->t_body->set_var("TEAM", $event['section']); 
                               			$this->t_body->set_var("OPP", $event['opp']);
                                        if($event['lat'] && $event['long'])
                                            $this->t_body->set_var("VENUE", '<a href="http://maps.google.com.au/maps?q='.$event['lat'].','.$event['long'].'" target="_blank">'.$event['venue'].'</a>');
                               			else
                                            $this->t_body->set_var("VENUE", $event['venue']);
                               			$this->t_body->set_var("MELREF", $event['melref']);
                                        $this->t_body->set_var("LAT", $event['lat']);
                                        $this->t_body->set_var("LONG", $event['long']);
						$field1 = $event['fname1']." ".$event['lname1'];
						$this->t_body->set_var("NAME0", $field1); 
						$this->t_body->set_var("PHONE0", $event['phone1']); 
						$this->t_body->set_var("EMAIL0", $event['email1']); 
						$field2 = $event['fname2']." ".$event['lname2'];
						$this->t_body->set_var("NAME1", $field2); 
						$this->t_body->set_var("PHONE1", $event['phone2']); 
						$this->t_body->set_var("EMAIL1", $event['email2']); 
						$this->t_body->set_var("TIME", $event['time']);

						if (!$prevdate) {
							//first time through
                               				$this->t_body->parse("event_listing", "event_list", false);
							if ($_SESSION['campus'][0] > 2){
								$this->t_body->parse("grimtime_sect", "grimtime", false);
								$this->t_body->set_var("normtime_sect", "");		
							}
							else { 
								$this->t_body->parse("normtime_sect", "normtime", false);		
								$this->t_body->set_var("grimtime_sect", "");
							}
							$this->t_body->parse("menu_heading", "menu_header", false);
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
					$isevents = 1;
                       		}
			}
			else
			{
				if (!$print){
					$isevents = 0;	
				}
			}
			if (!$print){
				if ($isevents)
                       			$this->t_body->parse("sport_listing", "sport_list", true);
			}
		}
		}
		else
			$this->t_body->set_var("sport_listing", "<font color=\"#FF0000\"><br>No listing available, current season may not be set<br><br>Please speak to sports administrators</font>");
		if ($noevents){
			$this->t_body->set_var("DATE", "");
                	$this->t_body->set_var("DISPLAY", "Sports Fixture");
			$this->t_body->set_var("sport_listing", "<font color=\"#FF0000\"><br>No events available</font>");
		}
		else
                	$this->t_body->set_var("DISPLAY", "Fixture for ".$fixlen);
		if ($print){
			$filestring.="</table></body></html>"; 
			savefile($filestring);
		}
		else
		{
                	$this->t_body->set_var("NAV", "");
			$this->t_body->parse("fixture_sect", "fixture", true);
			$this->t_body->pparse("finaloutput", "tplBODY");
		}
	}
	
	function array_column_sort($array, $column)
	{
		foreach ($array as $row)
		$sortarr[] = $row[$column];
		array_multisort($sortarr, $array);
		return $array;
	}
}  
$page = new sports_fixture($_GET['print']);
$page->displayFixture($_GET['type'], $_GET['id'], $_GET['print']);
?>
