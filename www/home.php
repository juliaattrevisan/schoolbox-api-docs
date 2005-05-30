<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

//displays list of files either by search or during browsing.

require_once("include/session.php");
require_once("include/include.php");
require_once("include/permission.php");
require_once("news/news.php");
require_once("weather/weather.php");
require_once("calendar/calendar.php");

class OutPut
{


	function OutPut()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "home.htm");

		$this->t_body->set_var("TITLE", HEADING." - Home");
		
		$this->t_body->set_block("tplBODY", "timetable", "timetable_block");	
		$this->t_body->set_block("tplBODY", "main_stories", "stories_block");
		$this->t_body->set_block("tplBODY", "weather", "weather_block");
		$this->t_body->set_block("tplBODY", "note_block", "note_listing");	

		$this->db = new dbManager;
		
		$this->setGreeting();

		//$this->set_var("DATE", date("F j, Y, g:i a"));
		include("include/page.php");
	}


	function set_var($varName, $varContents)
	{
		$this->t_body->set_var($varName, $varContents);
	}
	
	function greeting()
	{
		if(date("A")=="AM")
			return "Good Morning";
		else if(date("G") < 17)
			return "Good Afternoon";
		else
			return "Good Evening";
	}

	function setGreeting()
	{
		$query = "SELECT firstname FROM user WHERE 
				id = '".$_SESSION['id']."'";
		$user = $this->db->dbGetSingleRow($query);
		$this->set_var("GREETING", $this->greeting()." ".$user['firstname']);
	}

	function setStories($items)
	{
		$num_items = count($items['Id']);
		for ($i = 0; $i <= $num_items; $i++) 
		{ 
			$this->set_var("NEWS_TITLE", $items['Heading'][$i]);
			$this->set_var("NEWS_URL", "/news.php?id=".$items['Id'][$i]); 
			$this->t_body->parse("stories_block", "main_stories", true);
	    	}
		
		if ($num_items < 1)
        		$this->set_var("stories_block", "<tr><td width=\"20\">&nbsp;</td><td>No News items found</td></tr>");
	}

	function setDayEvents()
	{
		
		$cal = new Calendar;

		$currDay = date("d");
		$currMonth = date("m");
		$currYear = date("Y");

		$currentDay = mktime();
		
		$this->t_body->set_var("DATE", date("l jS",$currentDay));


                //first lets output submission events
                $sub = $cal->getSubmissionEvents($currDay, $currMonth, $currYear);
                for($i=0; $sub['category'][$i]; $i++)
                {
			$this->t_body->set_var("TIME", "");
			$this->t_body->set_var("NOTE", "*DUE WORK* ".$sub['category'][$i]." - ".$sub['name'][$i]); 
			$this->t_body->parse("note_listing","note_block", true);
                        $subout = 1;
                }

		$event = $cal->getAllDayEvents($currDay, $currMonth, $currYear);

                if(count($event['note']) < 1 && !$subout) {$this->t_body->set_var("note_listing", "");}
                else
                {
                        for($i=0; isset($event['note'][$i]); $i++)
                        {
				if($event['startTime'][$i] == "0:00" || strtolower($event['startTime'][$i]) == "12:00am" || strtolower($event['startTime'][$i]) == "12:00 am")
                                        $this->t_body->set_var("TIME", "");
                                else if($event['endTime'][$i] == "0:00" || strtolower($event['endTime'][$i]) == "12:00am" || strtolower($event['endTime'][$i]) == "12:00 am")
                                        $this->t_body->set_var("TIME", $event['startTime'][$i]);
                                else
                                        $this->t_body->set_var("TIME", $event['startTime'][$i]." - 
							".$event['endTime'][$i]);
                
				$this->t_body->set_var("NOTE", 
				"<font color=".$cal->displayColour($event['priority'][$i]).">".$event['note'][$i]."</font>");
				$this->t_body->parse("note_listing","note_block", true);

			}
                }
	}

	
	function setWeather()
	{
		$weather = new Weather("Current");
		
		$this->t_body->set_var("TEMP", $weather->Temp);
		$this->t_body->set_var("DIRECTION", $weather->WindC);
		$this->t_body->set_var("SPEED", $weather->WindS);
		$this->t_body->set_var("HUMIDITY", $weather->Humid);
		$this->t_body->set_var("ISSUED", date("g:i a", strtotime($weather->Date)));

		$this->t_body->parse("weather_block", "weather");
	}

	function setTimetable()
	{
		$this->t_body->set_block("timetable", "timetable_period", "timetable_period_block");
		$this->t_body->set_block("timetable", "timetable_header", "timetable_header_block");

		//setup database
		$db = mssql_connect(MSSQL_host,MSSQL_user,MSSQL_passwd);

		$year = date("Y");
		$synergy_id = $_SESSION['sid'];

		//need method for detecting what day it is
		include("syn_timetable.php");
		$daynumber = getDayNumber();
		
		$this->t_body->set_var("DAYNUMBER", $daynumber);
		//also get current peroid
		$periodNum = getPeriodNumber();

		//retrieve timetable defintion
		$timetabledef = unserialize(TIMETABLE_definition);

		if(isStudent($_SESSION['gid']))
                {
                        $query ="SELECT Timetable.Room, SubjectClasses.Description,
                                Timetable.PeriodNumber, Timetable.ClassCode
                                FROM SynergyOne.dbo.StudentClasses StudentClasses,
                                SynergyOne.dbo.SubjectClasses SubjectClasses, SynergyOne.dbo.Timetable
                                Timetable
                                WHERE Timetable.ClassCampus = SubjectClasses.ClassCampus AND
                                Timetable.ClassCode = SubjectClasses.ClassCode AND Timetable.FileSemester =
                                SubjectClasses.FileSemester AND Timetable.FileType = SubjectClasses.FileType
                                AND Timetable.FileYear = SubjectClasses.FileYear AND
                                StudentClasses.FileSemester = SubjectClasses.FileSemester AND
                                StudentClasses.FileSemester = Timetable.FileSemester AND
                                StudentClasses.FileType = SubjectClasses.FileType AND
                                StudentClasses.FileType = Timetable.FileType AND StudentClasses.FileYear =
                                SubjectClasses.FileYear AND StudentClasses.FileYear = Timetable.FileYear AND
                                StudentClasses.ClassCampus = SubjectClasses.ClassCampus AND
                                StudentClasses.ClassCode = SubjectClasses.ClassCode AND
                                ((Timetable.FileSemester=".$_SESSION['FileSemester'].") AND (Timetable.FileYear='$year') AND
                                (StudentClasses.ID='$synergy_id')) AND Timetable.DayNumber = '$daynumber'
                                ORDER BY Timetable.PeriodNumber";
		}
                else
                {
			$query = "SELECT Timetable.Room, SubjectClasses.Description,
                                Timetable.PeriodNumber, Timetable.ClassCode
                                FROM SynergyOne.dbo.SubjectClasses SubjectClasses, SynergyOne.dbo.Timetable
                                Timetable
                                WHERE Timetable.ClassCampus = SubjectClasses.ClassCampus AND
                                Timetable.ClassCode = SubjectClasses.ClassCode AND Timetable.FileSemester =
                                SubjectClasses.FileSemester AND Timetable.FileType = SubjectClasses.FileType
                                AND Timetable.FileYear = SubjectClasses.FileYear AND
                                ((SubjectClasses.FileSemester=".$_SESSION['FileSemester'].") AND (SubjectClasses.FileYear='$year') AND
                                (Timetable.StaffID='$synergy_id')) AND Timetable.DayNumber = '$daynumber'
                                ORDER BY Timetable.PeriodNumber";
		}
		
		$result = mssql_query($query, $db);
		if ($result != 1)
			$desc = mssql_fetch_array($result);
		else
			$desc = "";

		$periods =  $timetabledef[$_SESSION['campus'][0]]['period'];

		//for each period set result
		for($y=1; $y <= $periods; $y++)
                {
			if($desc[2] == ($y-1))
			{
				if ($result != 1)
					$desc = mssql_fetch_array($result);
				else
					$desc = "";
			}
			
			if($desc[2] != $y)
			{
				$code = "";
				$period = "-";
			}
			else
			{
				if ($_SESSION['campus'][0] == 3){
					$post = strpos($desc[3], "EN");
					if ($post == 2)
						$period = "Homeroom";
					else
						$period = $desc[1]."<br>".$desc[0]."<br>";
					$code = "";
				}
				else {
					$period = $desc[1]."<br>".$desc[0]."<br>";
					if(isStaff($_SESSION['gid']) && SITE_location == 'mgs')
						$period .= "<a href=\"/absentees/attendances.php?code=".$desc[3]."\">attendances</a><br>";
					$code = "onClick=\"window.location='/homepage.php?code=".urlencode($desc[3])."'\"";
             			}		
 				$desc = mssql_fetch_array($result);
			}
			$this->set_var("CODE", $code);
			$this->set_var("PERIOD", $period);
			if($y == $periodNum)
			{
				$this->set_var("TIMECOLOUR", "#0066CC");
				$this->set_var("TIMEOVERCOLOUR", "#0066CC");
			}
			else 
			{
				$this->set_var("TIMECOLOUR", "#EEEEEE");
				$this->set_var("TIMEOVERCOLOUR", "#CCCCCC");
			}
			
			//output period block
			$this->set_var("PERIOD_NUMBER", $y);
			$this->set_var("PERIOD_NAME", $timetabledef[$_SESSION['campus'][0]]['periodnames'][$y]);
			$this->t_body->parse("timetable_header_block", "timetable_header", true);
			$this->t_body->parse("timetable_period_block", "timetable_period", true);

		}

		$this->t_body->parse("timetable_block", "timetable");
	}


	function showPage()
	{
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

}

$myPage = new OutPut();

//if staff get all news.. 
if(isStaff($_SESSION['gid']))
        $topic = 0;
else //student only sees news for everyone
        $topic = 2;

$myNews = new news($topic);
//output headlines
$myPage->setStories($myNews->getHeadlines());

//if user has timetable show it on the homepage
if($_SESSION['timetable'])
	$myPage->setTimetable();
else
	$myPage->t_body->set_var("timetable_block", "");



//if weather module enabled output it
if(WEATHER_enable)
	$myPage->setWeather();
else
	$myPage->t_body->set_var("weather_block", "");

//output calendar
$myPage->setDayEvents();

$myPage->showPage();
?>
