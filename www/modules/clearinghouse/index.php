<?php

define("FOLDER_ID", 2587);

require_once('../../include/session.php');
require_once('../../include/include.php');
require_once('../../include/fileTypes.php');
require_once('event.php');

class displayHome
{
	function displayHome()
	{
		$this->db = new dbManager();
		
		$this->t_body = new Template(TPL_directory);
        $this->t_body->set_file('tplBODY', 'modules/clearinghouse/home.htm');
		$this->t_body->set_var('TITLE', HEADING." - PD Clearing House");
	
  		include_once("../../include/page.php");
		
		$this->folderID = FOLDER_ID;
		$this->t_body->set_var('FOLDER_ID', $this->folderID);
	
	}
	
	function upcomingEvents()
	{
		$this->t_body->set_block('tplBODY', 'event', 'event_block');
		$eventObj = new Event;
		$events = $eventObj->getUpcomingEvents(10);
		
		foreach($events as $event)
		{
			$tplVar = array(	'EVENT_NAME' => scrubTEXT($event['name']),
										'EVENT_ID' => $event['id'],
										'EVENT_COST' => scrubTEXT('$'.$event['cost']),
										'EVENT_DESC' => scrubTEXT($event['description']),
										'EVENT_ORGANISER' => scrubTEXT($event['organiser']),
										'EVENT_DATE' => date('l jS \of F Y g:ia', $event['start']),
									);
			
			$this->t_body->set_var($tplVar);
			$this->t_body->parse('event_block', 'event', true);
		}
		
	}

	function searchBox()
	{
		$query = "SELECT * FROM luYear";
		$year = $this->db->dbGetAllRows($query);
		array_unshift($year, array('name'=>'All'));
		$this->doDropDown('year', $year);
		
		$query = "SELECT * FROM luFormat";
		$format = $this->db->dbGetAllRows($query);
		array_unshift($format, array('name'=>'All'));
		$this->doDropDown('format', $format);
	}
	
	function news()
	{
		//news output
		$this->t_body->set_block('tplBODY', 'news', 'news_block');

		require("../../subject/subjectNews.php");
		$news = new news($this->folderID);
		
		$subnews = $news->getNews();
		if(is_array($subnews))
		{
			foreach($subnews as $article)
			{
				if($article['Owner'] == $_SESSION['id'] || $_SESSION['su'] == 1 || $this->perm == 3)
					$this->t_body->set_var("MOD_NEWS", "<a href=\"/subject/modSubNews.php?modify=".$article['Id']."\">Modify</a>");
				else
					$this->t_body->set_var("MOD_NEWS", "");
				$this->t_body->set_var("NEWS_HEADING", htmlentities($article['Heading']));
				$this->t_body->set_var("NEWS_BODY", $article['Body']);
				if($article['Contact'])
					$this->t_body->set_var("EMAIL_CONTACT", "<a href=\"{$_SESSION['mailhandle']}{$article['Contact']}\">{$article['Poster']}</a>");
				else
					$this->t_body->set_var("EMAIL_CONTACT", $article['Poster']);
		
				$this->t_body->set_var("POSTED_DATE", $article['Datetime']);
				$this->t_body->parse("news_block", "news", true);
			}
		}
		else
		{
			$this->t_body->set_var("news_block", "");
		}

		//setup block for submission button
		$this->t_body->set_block('tplBODY', 'submit_news', 'submit_news_block');
		//if($this->perm < 2)		
		//	$this->t_body->set_var("submit_news_block", "");
		//else
			$this->t_body->parse('submit_news_block', 'submit_news');	
	}
	
	function files()
	{
		//file output
		$this->t_body->set_block('tplBODY', 'files', 'files_block');
	
		$query = "SELECT id, description, realname, mime_type FROM intranet.file 
		WHERE category = '".$this->folderID."' AND file.deleted = '0' ORDER BY submitted DESC 
		LIMIT 0, 15";

		$files = $this->db->dbGetAllRows($query);

		if(is_array($files))
		{
			foreach($files as $file)
			{
				if($file['realname'] != "")
				{
					if($allowedFileTypes[$file['mime_type']][1] == 1)
						$url = "<a href=\"/sendframe.php?id=".$file['id']."\" target=\"_blank\">".scrubTEXT($file['description'])."</a>";
					else
						$url = "<a href=\"/send.php?id=".$file['id']."\">".scrubTEXT($file['description'])."</a>";
		
					$this->t_body->set_var("FILE_NAME", $url);
				}
				else
					$this->t_body->set_var("FILE_NAME", scrubTEXT($file['description']));
				
				$this->t_body->parse('files_block', 'files', true);
			}
		}
		else
			$this->t_body->set_var('files_block', '');
	}
	
	function outputPage()
	{
		$this->t_body->pparse('finaloutput','tplBODY');
	}

	/* PRIVATE */
	
	function doDropDown($name, $array)
	{
		$this->t_body->set_block('tplBODY', $name.'_select', $name.'var');
		
		foreach($array as $value)
		{
			$this->t_body->set_var(strtoupper($name).'_ID', $value[$name.'ID']);
			$this->t_body->set_var(strtoupper($name).'_NAME', $value['name']);
			$this->t_body->parse($name.'var', $name.'_select', true);
		}
	
	}

}

$page = new displayHome;
$page->upcomingEvents();
$page->searchBox();
$page->files();
$page->news();
$page->outputPage();

?>