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
require_once("calendar/calendar.php");
require_once("weather/weather.php");
require_once("news/whatsnew.php");

class OutPut
{
	function OutPut()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "home.htm");

		$this->t_body->set_var("TITLE", HEADING." - Home");
		
		$this->t_body->set_block("tplBODY", "timetable", "timetable_block");	
		
		$this->db = new dbManager;
		
		//set greeting
		$this->set_var("GREETING", $this->greeting() .' '. formatName($_SESSION));
		
		//if user has timetable show it on the homepage
		if($_SESSION['timetable'])
			$this->outputTimetable();
		else
			$this->t_body->set_var("timetable_block", "");

		//fetch layout for user
		$query = "SELECT position, name, filename, function 
					FROM welcomeLayout LEFT JOIN welcomeComponent ON componentID = welcomeComponent.id
					WHERE gid = {$_SESSION['gid']}";
		$result = $this->db->dbDoQuery($query);
		while($component = $this->db->dbGetNextArray($result))
		{
			if($component['name'])
			{
				$this->t_body->set_file($component['name'], 'home/'.$component['filename']);
				
				$funcname = $component['function'];
				if($funcname && $this->$funcname($component['name']))
					$this->t_body->parse('POS'.$component['position'], $component['name']);
				else 
					$this->t_body->set_var('POS'.$component['position'], '');
			}
			else 
				$this->t_body->set_var('POS'.$component['position'], '');
		}		
		$this->showPage();
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

	function outputNews($location)
	{	
		$this->t_body->set_block($location, "main_stories", "stories_block");
		
		$myNews = new news($topic);
		
		//output headlines
		$headlineList = $myNews->getHeadlines();
		
		if(is_array($headlineList) )
		{
			foreach($headlineList as $item)
			{
				$this->set_var("NEWS_TITLE", $item['item_heading']);
				$this->set_var("NEWS_URL", "/news.php#".$item['item_id']); 
				$this->t_body->parse("stories_block", "main_stories", true);
	    	}
		}
		else
		{
        	$this->set_var("stories_block", "<li>No news articles available</li>");
		}
		
		return true;
	}

	function outputCalendar($location)
	{
		$this->t_body->set_block($location, "note_block", "note_listing");
		
		$cal = new Calendar;
		
		$currDay = date("d");
		$currMonth = date("m");
		$currYear = date("Y");
		
		$this->t_body->set_var("DATE", date("l jS F"));
		
		//first lets output submission events
		$sub = $cal->getSubmissionEvents($currDay, $currMonth, $currYear);
		for($i=0; $sub['category'][$i]; $i++)
		{
			$this->t_body->set_var("TIME", $sub['dueTime'][$i]);
			if($sub['synergyCode'][$i])
				$descr = $sub['category'][$i]." (".$sub['synergyCode'][$i].") - ".$sub['description'][$i];
			else
				$descr = $sub['category'][$i]." - ".$sub['description'][$i];
				
			$this->t_body->set_var("NOTE", "WORK DUE ".$descr); 
			$this->t_body->set_var("NOTE_COLOUR", $cal->displayColour(1));
			
			$this->t_body->parse("note_listing","note_block", true);
			$subout = 1;
		}
		
		$eventList = $cal->getAllDayEvents($currDay, $currMonth, $currYear);
		
		if(!is_array($eventList))
		{
			if(!$subout) $this->t_body->set_var("note_listing", "");
		}
		else
		{
			foreach($eventList as $event)
			{
				if($event['startTime'] == "0:00" || strtolower($event['startTime']) == "12:00am" || strtolower($event['startTime']) == "12:00 am" || $event['startTime'] == '')
						$this->t_body->set_var("TIME", "");
				else if($event['endTime'] == "0:00" || strtolower($event['endTime']) == "12:00am" || strtolower($event['endTime']) == "12:00 am" || $event['endTime'] == '')
						$this->t_body->set_var("TIME", $event['startTime']);
				else
						$this->t_body->set_var("TIME", $event['startTime']." - ".$event['endTime']);
				
				$this->t_body->set_var("NOTE_COLOUR", $cal->displayColour($event['priority']));
				$this->t_body->set_var("NOTE", $event['note']);
				$this->t_body->parse("note_listing","note_block", true);

			}
		}
		
		return true;
	}

	
	function outputWeather($location)
	{
		//dont output weather if not enabled
		if(!WEATHER_enable)
			return false;

		$weather = new Weather("Current");
		
		$this->t_body->set_var("WEATHER_ICON", $weather->forecast[0]["Icon"]);
		$this->t_body->set_var("PRECIS", $weather->forecast[0]["Precis"]);
		$this->t_body->set_var("TEMP", $weather->Temp);
		$this->t_body->set_var("DIRECTION", $weather->WindCompass);
		$this->t_body->set_var("SPEED", $weather->WindSpeed);
		$this->t_body->set_var("HUMIDITY", $weather->Humid);
		$this->t_body->set_var("ISSUED", date("g:i a", strtotime(str_replace('T', ' ',$weather->Date))));
		
		return true;
	}

	function outputTimetable()
	{
		$this->t_body->set_block("timetable", "timetable_period", "timetable_period_block");
		$this->t_body->set_block("timetable", "timetable_header", "timetable_header_block");

		include("include/syn_timetable.php");
		
		//get current day and period
		$daynumber = getDayNumber($_SESSION['campus'][0]);
		$period = getPeriodNumber($_SESSION['campus'][0]);
		$periodNum = $period['PeriodNumber'];
		
		$this->t_body->set_var("DAYNUMBER", $daynumber);
	
		$timetable = getDayTimetable($_SESSION['sid'], $_SESSION['gid'], $_SESSION['campus'][0], $daynumber);
		
		$periods = count($timetable);

		//timetable field width
		$this->t_body->set_var("TIMETABLE_FIELD_WIDTH", round((100 / $periods),1));

		//for each period set result
		foreach($timetable as $period)
		{
			if($period['periodSequence'] == 1)
			{
				$this->t_body->set_var("PERIOD_NAME", $period['periodName']);
				$this->t_body->set_var("PERIOD_START", $period['periodStart']);
				$this->t_body->set_var("PERIOD_END", $period['periodEnd']);
				
				if($period['description'])
				{
					//custom stuff for grimwade
					if ($_SESSION['campus'][0] == 3 && SITE_location == 'mgs')
					{
						$post = strpos($period['code'], "EN");
							
						if ($post == 2)
						{
							$this->set_var('PERIOD_DESC', 'Homeroom');
							$this->set_var('PERIOD_CODE','');
							$this->set_var('PERIOD_ROOM','');
						}
						else
						{
							$this->set_var('PERIOD_DESC', $period['description']);
							$this->set_var('PERIOD_CODE','');
							$this->set_var('PERIOD_ROOM',$period['room']);
						}
						
						$this->set_var('PERIOD_ABSENTEES','');
					}
					else if($_SESSION['campus'][0] == 2 && SITE_location == 'wenona')
					{
							$this->set_var('PERIOD_DESC', $period['description']);
							$this->set_var('PERIOD_CODE','');
							$this->set_var('PERIOD_ROOM',$period['room']);
							$this->set_var('PERIOD_ABSENTEES','');
					}
					else 
					{
						$this->set_var('PERIOD_DESC', "<a 
href=\"/homepage.php?code=".urlencode($period['code'])."\">{$period['description']}</a>");
						$this->set_var('PERIOD_CODE','('.$period['code'].')');
						$this->set_var('PERIOD_ROOM',$period['room']);
						
						//only show attendances to staff at mgs
						if(isStaff($_SESSION['gid']) && SITE_location == 'mgs')
							$this->set_var('PERIOD_ABSENTEES',"<br /><a href=\"/absentees/attendances.php?code=".$period['code']."\">attendances</a>");
						else
							$this->set_var('PERIOD_ABSENTEES','');
							
					}		
				
				}
				else
				{
					$this->set_var('PERIOD_DESC','');
					$this->set_var('PERIOD_CODE','');
					$this->set_var('PERIOD_ROOM','');
					$this->set_var('PERIOD_ABSENTEES','');
					$this->set_var('PERIOD_ONCLICK','');
				}
			
				if($period['periodNumber'] == $periodNum)
					$this->set_var("TIMETABLE_CLASS", "current");
				else 
					$this->set_var("TIMETABLE_CLASS", "normal");
			
			
				//output period block
				$this->t_body->parse("timetable_header_block", "timetable_header", true);
				$this->t_body->parse("timetable_period_block", "timetable_period", true);
			}
		}
		$this->t_body->parse("timetable_block", "timetable");
	}
	
	function outputWhatNew($location)
	{
		$catList = involvedCategoryList($_SESSION['id'], $_SESSION['subjects'], 1);
		$whatnew = new WhatsNew();
	

		$this->t_body->set_block($location, 'category_block', 'new_category');
		$this->t_body->set_block('category_block', 'due_work', 'due_block');
		$this->t_body->set_block('category_block', 'ret_work', 'ret_block');
		$this->t_body->set_block('category_block', 'sub_work', 'sub_block');
		$this->t_body->set_block('category_block', "new_news", "new_news_item"); 
		$this->t_body->set_block("category_block", "new_file", "new_file_item"); 
		$this->t_body->set_block("category_block", "new_url", "new_url_item"); 
		$this->t_body->set_block("category_block", "new_forum", "new_forum_item"); 
		$flag = 0;

		

		//for each subject
		if(is_array($catList))
		{
			foreach($catList as $catInfo)
			{
				$sub = $whatnew->submissionDetails($catInfo['id']);
				if($sub['duework'])
					$this->t_body->parse('due_block', 'due_work');
				else
					$this->t_body->set_var('due_block', '');
				
				if($sub['retwork']) 
					$this->t_body->parse('ret_block', 'ret_work');
				else
					$this->t_body->set_var('ret_block', '');
					
				if($sub['subboxes'])
					$this->t_body->parse('sub_block', 'sub_work');
				else
					$this->t_body->set_var('sub_block', '');
	
			
				$cat = $whatnew->newCategory($catInfo['id']);
				if($cat['news'])
				{
					if($cat['news'] > 1)
						$this->t_body->set_var('NEW_PLURAL_NEWS', 's');
					else
						$this->t_body->set_var('NEW_PLURAL_NEWS', '');
					$this->t_body->set_var("NEW_NEWS", $cat['news']);
					$this->t_body->parse("new_news_item", "new_news");
				}
				else
					$this->t_body->set_var("new_news_item", "");
					
				if($cat['files'])
				{
					if($cat['files'] > 1)
						$this->t_body->set_var('NEW_PLURAL_FILE', 's');
					else
						$this->t_body->set_var('NEW_PLURAL_FILE', '');
					$this->t_body->set_var("NEW_FILES", $cat['files']);
					$this->t_body->parse("new_file_item", "new_file");
				}
				else
					$this->t_body->set_var("new_file_item", "");
				
				if($cat['links'])
				{
					if($cat['links'] > 1)
						$this->t_body->set_var('NEW_PLURAL_LINK', 's');
					else
						$this->t_body->set_var('NEW_PLURAL_LINK', '');
					$this->t_body->set_var("NEW_URLS", $cat['links']);
					$this->t_body->parse("new_url_item", "new_url");
				}
				else
					$this->t_body->set_var("new_url_item", "");
				
				if($cat['forum'])
				{
					if($cat['forum'] > 1)
						$this->t_body->set_var('NEW_PLURAL_FORUM', 's');
					else
						$this->t_body->set_var('NEW_PLURAL_FORUM', '');
					$this->t_body->set_var("NEW_FORUM", $cat['forum']);
					$this->t_body->parse("new_forum_item", "new_forum");
				}
				else
					$this->t_body->set_var("new_forum_item", "");
				
				
				if($cat['news'] || $cat['files'] || $cat['links'] || $cat['forum'])
				{
					if($catInfo['code'])
						$this->t_body->set_var("NEW_CATEGORY_NAME", $catInfo['name'].' ('.$catInfo['code'].')');
					else
						$this->t_body->set_var("NEW_CATEGORY_NAME", $catInfo['name']);
					$this->t_body->set_var("NEW_CATEGORY_ID", $catInfo['id']);
					$this->t_body->parse("new_category", "category_block", true);
					$flag = 1;
				}
				
			}
		}
		
		if(!$flag)
		{
			$this->t_body->set_var('new_category','');
			return false;
		}
		
		return true;
	}

	function outputChildSubjects($location)
	{
		$this->t_body->set_block($location, "student_block", "student_subjects");
		$this->t_body->set_block("student_block", "subject_block", "subject_item");

		if(is_array($_SESSION['children'])) {
			foreach($_SESSION['children'] as $child)
			{
				$query = "SELECT title, firstname, lastname, year FROM user WHERE id = {$child['id']}";
				$user = $this->db->dbGetSingleRow($query);
				$this->t_body->set_var("STUDENT_ID", $child['id']);
				$this->t_body->set_var("STUDENT_NAME", formatName($user,'{firstname} {lastname}'));
				$this->t_body->set_var("STUDENT_YEAR", $user['year']);
				
				if(is_array($child['subjects']) ) {
					foreach($child['subjects'] as $subject)
					{
						$this->t_body->set_var("STUDENT_SUBJECT_NAME", $subject['name']);
						$this->t_body->set_var("STUDENT_SUBJECT_CODE", $subject['code']);
						$this->t_body->parse("subject_item", "subject_block", true);
					}
				}
	
				$this->t_body->parse("student_subjects", "student_block", true);
				$this->t_body->set_var("subject_item", '');
			
			}
			return true;
		}
		
		return false;
	}
	
	function outputWenonaIntranet($location) {
		return true;
	}
	
	function outputHelpdesk($location) {
		if(HELPDESK_enable) {
			$this->t_body->set_block($location, "helpdesk_block", "helpdesk_jobs");
			
			$jobs = $this->openHelpdeskJobs($_SESSION['id']);
			
			if(is_array($jobs)){
				foreach ($jobs as $job) {
					$this->t_body->set_var("PROBLEM", ucfirst($job['problem']));
					$this->t_body->set_var("CREATED", $job['createdDate']);
					$this->t_body->set_var("STATUS", $job['status']);
					$this->t_body->parse("helpdesk_jobs", "helpdesk_block", true);
				}
			
				return true;
			}
		}
		
		return false;
	}
	
	function outputParentHelpdesk($location) {
		if(HELPDESK_enable) {
			$this->t_body->set_block($location, "student_helpdesk_block", "student_helpdesk");
			$this->t_body->set_block("student_helpdesk_block", "student_job_block", "student_job");
			if(is_array($_SESSION['children'])) {
				foreach($_SESSION['children'] as $child)
				{
					$query = "SELECT title, firstname, lastname, year FROM user WHERE id = {$child['id']}";
					$user = $this->db->dbGetSingleRow($query);
					$this->t_body->set_var("STUDENT_ID", $child['id']);
					$this->t_body->set_var("STUDENT_NAME", formatName($user,'{firstname} {lastname}'));
					$this->t_body->set_var("STUDENT_YEAR", $user['year']);
					
					$jobs = $this->openHelpdeskJobs($child['id']);
				
					if(is_array($jobs)){
						foreach ($jobs as $job) {
							$this->t_body->set_var("PROBLEM", ucfirst($job['problem']));
							$this->t_body->set_var("CREATED", $job['createdDate']);
							$this->t_body->set_var("STATUS", $job['status']);
							$this->t_body->parse("student_job", "student_job_block", true);
						}
					}
				
					$this->t_body->parse("student_helpdesk", "student_helpdesk_block", true);
					$this->t_body->set_var("student_job", '');
				}
				return true;
			}
		}
		return false;
	}
	
	function openHelpdeskJobs($userid) {
			$query = "
		SELECT  j.jobID, 
				j.portalAssignedUserID,
				concat(assignedUser.firstname, ' ', assignedUser.lastname) as assignedUserName,
				p.description as problem,
				j.description,
				DATE_FORMAT(j.createdDate, '%e/%c/%Y') as createdDate,
				s.description as status
		FROM    tblJob j
		INNER JOIN
				tblProblem p on j.problemID = p.problemID
		INNER JOIN
				tblStatus s on j.statusID = s.statusID
		LEFT JOIN
				tblEquipment e on j.equipmentID = e.equipmentID
		LEFT JOIN
				".SQL_db.".user assignedUser on assignedUser.id = j.portalAssignedUserID
		WHERE s.description != 'Closed' AND (j.portalAssociatedUserID = {$userid} OR e.portalUserID ={$userid} ) 
		ORDER BY j.createdDate DESC";
		
		$this->db->dbChangedb(HELPDESK_db);
		
		$jobs = $this->db->dbGetAllRows($query);
		$this->db->dbChangedb(SQL_db);
		
		return $jobs;
	}
	
	function outputRSS($location) {
		$this->t_body->set_block($location, "rss_stories", "rss_block");
		
		require_once('include/lastRSS.php');
		$rss = new lastRSS; 
		
		$rss->cache_dir = RSS_cache_dir; 
  		$rss->cache_time = RSS_cache_time; // one hour
		$rss->items_limit = RSS_limit;
		$headlineList = $rss->get(RSS_url);
			
		$this->set_var('RSS_NAME', $headlineList['title']);
		
		//output headlines
		if(is_array($headlineList['items']) ) {
			foreach($headlineList['items'] as $item) {
				$this->set_var("RSS_TITLE", $item['title']);
				$this->set_var("RSS_URL", $item['link']); 
				$this->set_var("RSS_BRIEF", $item['description']);
				$this->t_body->parse("rss_block", "rss_stories", true);
	    	}
		}
		else
		{
			return false;
		}
		
		return true;
	}

	function showPage()
	{
		include("include/page.php");
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

}

$myPage = new OutPut();

?>
