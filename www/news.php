<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//displays news

require_once("include/session.php");
require_once("include/include.php");
require_once("include/permission.php");
require_once("news/news.php");

class OutPut
{
	function OutPut()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "news.htm");

		$this->t_body->set_var("TITLE", HEADING." - News");
		
		$this->t_body->set_block("tplBODY", "main_stories", "stories_block");
    		$this->t_body->set_block("main_stories", "story_body", "story_block");


		//$this->set_var("DATE", date("F j, Y, g:i a"));

		include("include/page.php");
	}

	function set_var($varName, $varContents)
	{
		$this->t_body->set_var($varName, $varContents);
	}
	
	function setStories($items)
	{
		//can see add news link
		if(isStaff($_SESSION['gid']))
			$this->set_var("ADD", "<b><a href=\"/news/modnews.php\">add news</a></b>");
		else
			$this->set_var("ADD", "");
	
		$num_items = count($items['Id']);
		for ($i = 1; $i <= $num_items; $i++) 
		{ 
         
			if($items['Owner'][$i] == $_SESSION['id'] || $_SESSION['su'] == 1)
				$this->set_var("MODIFY", "<a href=\"/news/modnews.php?id=".$items['Id'][$i]."\">modify</a>");
			else
				$this->set_var("MODIFY", "");
			
			$this->set_var("NEWS_TITLE", $items['Heading'][$i]);
		
			//TODO: Fix news handle to support no email address
			if($items['Contact'][$i])
				$email = "<a href=\"".$_SESSION['mailhandle'].$items['Contact'][$i]."\">".$items['Poster'][$i]."</a>";
			else
				$email = $items['Poster'][$i];

			$this->set_var("POSTER", $email);
			if($_SESSION['su'] == 1)
				$this->set_var("DATETIME", $items['Datetime'][$i]." expires on ".$items['Expires'][$i]);
			else
				$this->set_var("DATETIME", $items['Datetime'][$i]);
			$totalBody = preg_replace("/(\015\012)|(\015)|(\012)/","<br />",scrubHTML($items['Body'][$i]));
                
			$this->set_var("NEWS_BODY", $totalBody);
                	$this->t_body->parse("story_block", "story_body");

			$this->t_body->parse("stories_block", "main_stories", true);

        }
		if ($num_items < 1)
        		$this->set_var("stories_block", "No news items found");
	}

	function showPage()
	{
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

}

$myPage = new OutPut();

//if staff get all news.. 
if(isStaff($_SESSION['gid']) )
	$topic = 0;
else //student only sees news for everyone
	$topic = 2;


$myNews = new news($topic);

if(isset($_GET['id']) && $_GET['id'])
{
	//show just a single story
	$myPage->setStories($myNews->getNews($_GET['id']));	
}
else
{
	//show all stories
	$myPage->setStories($myNews->getNews());
}


$myPage->showPage();
?>
