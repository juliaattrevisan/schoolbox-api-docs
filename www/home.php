<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

//displays list of files either by search or during browsing.

//require_once("include/permission.php");
//require_once("news/news.php");
//require_once("calendar/calendar.php");
//require_once("weather/weather.php");
//require_once("news/whatsnew.php");


$this->setFile(TEMPLATE_content, $this->getTemplate('home.html'));

$this->setVar("TITLE", HEADING." - Home");

$this->setBlock(TEMPLATE_content, "timetable", "timetable_block");	

if(date("A")=="AM")
	$greeting =  "Good Morning";
else if(date("G") < 17)
	$greeting =  "Good Afternoon";
else
	$greeting =  "Good Evening";

//set greeting
$this->setVar("GREETING", $greeting .' '. formatName($_SESSION));

//if user has timetable show it on the homepage
if($_SESSION['timetable'])
	$this->outputTimetable();
else
	$this->setVar("timetable_block", "");

//fetch layout for user
$query = "SELECT position, name, filename, function 
			FROM welcomeLayout LEFT JOIN welcomeComponent ON componentID = welcomeComponent.id
			WHERE gid = {$_SESSION['gid']}";
$result = $this->db->query($query);
while($component = $this->db->getNextArray($result))
{
	if($component['name'])
	{
		$this->setFile($component['name'], $this->getTemplate('home/'.$component['filename']));
		
		$funcname = $component['function'];
		if(is_file($_SERVER['DOCUMENT_ROOT'].'/home/'.$funcname.'.php') && $_SERVER['DOCUMENT_ROOT'].'/home/'.$funcname.'.php'))
			$this->parse('POS'.$component['position'], $component['name']);
		else 
			$this->setVar('POS'.$component['position'], '');
	}
	else 
		$this->setVar('POS'.$component['position'], '');
}	


	
function outputNews($location)
{	
	$this->setBlock($location, "main_stories", "stories_block");
	
	$myNews = new news($topic);
	
	//output headlines
	$headlineList = $myNews->getHeadlines();
	
	if(is_array($headlineList) )
	{
		foreach($headlineList as $item)
		{
			$this->set_var("NEWS_TITLE", $item['item_heading']);
			$this->set_var("NEWS_URL", "/news.php#".$item['item_id']); 
			$this->parse("stories_block", "main_stories", true);
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
	$this->setBlock($location, "note_block", "note_listing");
	
	$cal = new Calendar;
	
	$currDay = date("d");
	$currMonth = date("m");
	$currYear = date("Y");
	
	$this->setVar("DATE", date("l jS F"));
	
	//first lets output submission events
	$sub = $cal->getSubmissionEvents($currDay, $currMonth, $currYear);
	for($i=0; $sub['category'][$i]; $i++)
	{
		$this->setVar("TIME", $sub['dueTime'][$i]);
		if($sub['synergyCode'][$i])
			$descr = $sub['category'][$i]." (".$sub['synergyCode'][$i].") - ".$sub['description'][$i];
		else
			$descr = $sub['category'][$i]." - ".$sub['description'][$i];
			
		$this->setVar("NOTE", "WORK DUE ".$descr); 
		$this->setVar("NOTE_COLOUR", $cal->displayColour(1));
		
		$this->parse("note_listing","note_block", true);
		$subout = 1;
	}
	
	$eventList = $cal->getAllDayEvents($currDay, $currMonth, $currYear);
	
	if(!is_array($eventList))
	{
		if(!$subout) $this->setVar("note_listing", "");
	}
	else
	{
		foreach($eventList as $event)
		{
			if($event['startTime'] == "0:00" || strtolower($event['startTime']) == "12:00am" || strtolower($event['startTime']) == "12:00 am" || $event['startTime'] == '')
					$this->setVar("TIME", "");
			else if($event['endTime'] == "0:00" || strtolower($event['endTime']) == "12:00am" || strtolower($event['endTime']) == "12:00 am" || $event['endTime'] == '')
					$this->setVar("TIME", $event['startTime']);
			else
					$this->setVar("TIME", $event['startTime']." - ".$event['endTime']);
			
			$this->setVar("NOTE_COLOUR", $cal->displayColour($event['priority']));
			$this->setVar("NOTE", $event['note']);
			$this->parse("note_listing","note_block", true);

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
	
	$this->setVar("WEATHER_ICON", $weather->forecast[0]["Icon"]);
	$this->setVar("PRECIS", $weather->forecast[0]["Precis"]);
	$this->setVar("TEMP", $weather->Temp);
	$this->setVar("DIRECTION", $weather->WindCompass);
	$this->setVar("SPEED", $weather->WindSpeed);
	$this->setVar("HUMIDITY", $weather->Humid);
	$this->setVar("ISSUED", date("g:i a", strtotime(str_replace('T', ' ',$weather->Date))));
	
	return true;
}

function outputTimetable()
{
	$this->setBlock("timetable", "timetable_period", "timetable_period_block");
	$this->setBlock("timetable", "timetable_header", "timetable_header_block");

	include("include/syn_timetable.php");
	
	//get current day and period
	$daynumber = getDayNumber($_SESSION['campus'][0]);
	$period = getPeriodNumber($_SESSION['campus'][0]);
	$periodNum = $period['PeriodNumber'];
	
	$this->setVar("DAYNUMBER", $daynumber);

	$timetable = getDayTimetable($_SESSION['sid'], $_SESSION['gid'], $_SESSION['campus'][0], $daynumber);
	
	$periods = count($timetable);

	//timetable field width
	$this->setVar("TIMETABLE_FIELD_WIDTH", round((100 / $periods),1));

	//for each period set result
	foreach($timetable as $period)
	{
		if($period['periodSequence'] == 1)
		{
			$this->setVar("PERIOD_NAME", $period['periodName']);
			$this->setVar("PERIOD_START", $period['periodStart']);
			$this->setVar("PERIOD_END", $period['periodEnd']);
			
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
			$this->parse("timetable_header_block", "timetable_header", true);
			$this->parse("timetable_period_block", "timetable_period", true);
		} else {
			$this->setVar("timetable_header_block", "");
			$this->t_body->set_vat("timetable_period_block", "");
		}
	} 
	$this->parse("timetable_block", "timetable");
}

function outputWhatNew($location)
{
	$catList = involvedCategoryList($_SESSION['id'], $_SESSION['subjects'], 1);
	$whatnew = new WhatsNew();


	$this->setBlock($location, 'category_block', 'new_category');
	$this->setBlock('category_block', 'due_work', 'due_block');
	$this->setBlock('category_block', 'ret_work', 'ret_block');
	$this->setBlock('category_block', 'sub_work', 'sub_block');
	$this->setBlock('category_block', "new_news", "new_news_item"); 
	$this->setBlock("category_block", "new_file", "new_file_item"); 
	$this->setBlock("category_block", "new_url", "new_url_item"); 
	$this->setBlock("category_block", "new_forum", "new_forum_item"); 
	$flag = 0;

	

	//for each subject
	if(is_array($catList))
	{
		foreach($catList as $catInfo)
		{
			$sub = $whatnew->submissionDetails($catInfo['id']);
			if($sub['duework'])
				$this->parse('due_block', 'due_work');
			else
				$this->setVar('due_block', '');
			
			if($sub['retwork']) 
				$this->parse('ret_block', 'ret_work');
			else
				$this->setVar('ret_block', '');
				
			if($sub['subboxes'])
				$this->parse('sub_block', 'sub_work');
			else
				$this->setVar('sub_block', '');

		
			$cat = $whatnew->newCategory($catInfo['id']);
			if($cat['news'])
			{
				if($cat['news'] > 1)
					$this->setVar('NEW_PLURAL_NEWS', 's');
				else
					$this->setVar('NEW_PLURAL_NEWS', '');
				$this->setVar("NEW_NEWS", $cat['news']);
				$this->parse("new_news_item", "new_news");
			}
			else
				$this->setVar("new_news_item", "");
				
			if($cat['files'])
			{
				if($cat['files'] > 1)
					$this->setVar('NEW_PLURAL_FILE', 's');
				else
					$this->setVar('NEW_PLURAL_FILE', '');
				$this->setVar("NEW_FILES", $cat['files']);
				$this->parse("new_file_item", "new_file");
			}
			else
				$this->setVar("new_file_item", "");
			
			if($cat['links'])
			{
				if($cat['links'] > 1)
					$this->setVar('NEW_PLURAL_LINK', 's');
				else
					$this->setVar('NEW_PLURAL_LINK', '');
				$this->setVar("NEW_URLS", $cat['links']);
				$this->parse("new_url_item", "new_url");
			}
			else
				$this->setVar("new_url_item", "");
			
			if($cat['forum'])
			{
				if($cat['forum'] > 1)
					$this->setVar('NEW_PLURAL_FORUM', 's');
				else
					$this->setVar('NEW_PLURAL_FORUM', '');
				$this->setVar("NEW_FORUM", $cat['forum']);
				$this->parse("new_forum_item", "new_forum");
			}
			else
				$this->setVar("new_forum_item", "");
			
			
			if($sub['duework'] || $sub['retwork'] || $sub['subboxes'] || $cat['news'] || $cat['files'] || $cat['links'] || $cat['forum'])
			{
				if($catInfo['code'])
					$this->setVar("NEW_CATEGORY_NAME", $catInfo['name'].' ('.$catInfo['code'].')');
				else
					$this->setVar("NEW_CATEGORY_NAME", $catInfo['name']);
				$this->setVar("NEW_CATEGORY_ID", $catInfo['id']);
				$this->parse("new_category", "category_block", true);
				$flag = 1;
			}
			
		}
	}
	
	if(!$flag)
	{
		$this->setVar('new_category','');
		return false;
	}
	
	return true;
}

function outputChildSubjects($location)
{
	$this->setBlock($location, "student_block", "student_subjects");
	$this->setBlock("student_block", "subject_block", "subject_item");

	if(is_array($_SESSION['children'])) {
		foreach($_SESSION['children'] as $child)
		{
			$query = "SELECT title, firstname, lastname, year FROM user WHERE id = {$child['id']}";
			$user = $this->db->getRow($query);
			$this->setVar("STUDENT_ID", $child['id']);
			$this->setVar("STUDENT_NAME", formatName($user,'{firstname} {lastname}'));
			$this->setVar("STUDENT_YEAR", $user['year']);
			
			if(is_array($child['subjects']) ) {
				foreach($child['subjects'] as $subject)
				{
					$this->setVar("STUDENT_SUBJECT_NAME", $subject['name']);
					$this->setVar("STUDENT_SUBJECT_CODE", $subject['code']);
					$this->parse("subject_item", "subject_block", true);
				}
			}

			$this->parse("student_subjects", "student_block", true);
			$this->setVar("subject_item", '');
		
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
		$this->setBlock($location, "helpdesk_block", "helpdesk_jobs");
		
		$jobs = $this->openHelpdeskJobs($_SESSION['id']);
		
		if(is_array($jobs)){
			foreach ($jobs as $job) {
				$this->setVar("PROBLEM", ucfirst($job['problem']));
				$this->setVar("CREATED", $job['createdDate']);
				$this->setVar("STATUS", $job['status']);
				$this->parse("helpdesk_jobs", "helpdesk_block", true);
			}
		
			return true;
		}
	}
	
	return false;
}

function outputParentHelpdesk($location) {
	if(HELPDESK_enable) {
		$this->setBlock($location, "student_helpdesk_block", "student_helpdesk");
		$this->setBlock("student_helpdesk_block", "student_job_block", "student_job");
		if(is_array($_SESSION['children'])) {
			foreach($_SESSION['children'] as $child)
			{
				$query = "SELECT title, firstname, lastname, year FROM user WHERE id = {$child['id']}";
				$user = $this->db->getRow($query);
				$this->setVar("STUDENT_ID", $child['id']);
				$this->setVar("STUDENT_NAME", formatName($user,'{firstname} {lastname}'));
				$this->setVar("STUDENT_YEAR", $user['year']);
				
				$jobs = $this->openHelpdeskJobs($child['id']);
			
				if(is_array($jobs)){
					foreach ($jobs as $job) {
						$this->setVar("PROBLEM", ucfirst($job['problem']));
						$this->setVar("CREATED", $job['createdDate']);
						$this->setVar("STATUS", $job['status']);
						$this->parse("student_job", "student_job_block", true);
					}
				}
			
				$this->parse("student_helpdesk", "student_helpdesk_block", true);
				$this->setVar("student_job", '');
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
	
	$jobs = $this->db->getAllRows($query);
	$this->db->dbChangedb(SQL_db);
	
	return $jobs;
}

function outputRSS($location) {
	$this->setBlock($location, "rss_stories", "rss_block");
	
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
			$this->set_var("RSS_TITLE", scrubTEXT($item['title']));
			$this->set_var("RSS_URL", scrubHTML($item['link'])); 
			$this->set_var("RSS_BRIEF", scrubTEXT($item['description']));
			$this->parse("rss_block", "rss_stories", true);
		}
	}
	else
	{
		return false;
	}
	
	return true;
}


?>
