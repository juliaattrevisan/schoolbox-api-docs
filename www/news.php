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
		$this->t_body->set_file("tplBODY", "news/news.htm");

		$this->t_body->set_var("TITLE", HEADING." - News");
		
		$this->t_body->set_block("tplBODY", "main_stories", "stories_block");
    	$this->t_body->set_block("main_stories", "story_body", "story_block");
		
		$this->t_body->set_block('tplBODY', 'previous_button', 'previous_button_block');
		$this->t_body->set_block('tplBODY', 'page_number', 'page_number_block');
		$this->t_body->set_block('tplBODY', 'next_button', 'next_button_block');	
		$this->t_body->set_block('tplBODY', 'archive_button', 'archive_button_block');	
		
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
			
		if(is_array($items))
		{
			foreach($items as $article)
			{
				$this->set_var("NEWS_ID",$article['item_id']); 
        	 
				if($article['item_owner'] == $_SESSION['id'] || $_SESSION['su'] == 1)
					$this->set_var("MODIFY", "<a href=\"/news/modnews.php?id=".$article['item_id']."\">modify</a>");
				else
					$this->set_var("MODIFY", "");
				
				$this->set_var("NEWS_TITLE", $article['item_heading']);
				
				$fullname = formatName($article);
				$owner = "<a href=\"/search/userLookup.php?id={$article['uid']}\">$fullname</a>";
	
				$this->set_var("POSTER", $owner);
				
				if($_SESSION['su'] == 1)
					$this->set_var("DATETIME", $article['post_datetime']." expires on ".$article['expire_datetime']);
				else
					$this->set_var("DATETIME", $article['post_datetime']);
					
				$this->set_var("NEWS_BODY", nl2br(scrubHTML($article['item_body'])));
				
				$this->t_body->parse("story_block", "story_body");
				$this->t_body->parse("stories_block", "main_stories", true);
	
			}
		}
		else
		{
        		$this->set_var("stories_block", "No news items found");
		}
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

if (isset($_GET['archived'])) {
	$show = "archived";
	$myPage->t_body->set_var('archive_button_block', '');
} else {
	$show = "current";
	$myPage->t_body->parse('archive_button_block', 'archive_button');
}

$myNews = new news($topic, $show);

if(isset($_GET['id']) && $_GET['id'])
{
	//show just a single story
	$myPage->setStories($myNews->getNews($_GET['id']));	
}
elseif (isset($_GET['archived']))
{
	$pageNum = $_GET['pageNum'];

	// output pagination
	$newsList = $myNews->getNews();

	if(!$pageNum)
		$pageNum = 1;
		
	$newsPerPage = 15;
	$totalPages = ceil(count($newsList)/$newsPerPage);
	$newsIndex = $newsPerPage * ($pageNum-1);		
	
	if($pageNum<2)	
		$myPage->t_body->set_var('previous_button_block', '');
	else
		$myPage->t_body->parse('previous_button_block', 'previous_button');
	
	if($pageNum >= $totalPages)
		$myPage->t_body->set_var('next_button_block', '');
	else
		$myPage->t_body->parse('next_button_block', 'next_button');
	
	if($totalPages > 1) {
		for($j=1; $j <= $totalPages; $j++) {
			if($pageNum == $j)
				 $myPage->t_body->set_var('CURRENT', ' current');
			else
				 $myPage->t_body->set_var('CURRENT', '');
				 
			$myPage->t_body->set_var('PAGE_INDEX', $j);
			$myPage->t_body->parse('page_number_block','page_number', true); 
		}
	}
	else
		$myPage->t_body->set_var('page_number_block','');

	$outputPagination = array('PREVIOUS_PAGE' 	=> $pageNum - 1,
							  'NEXT_PAGE'		=> $pageNum + 1,
							  'LAST_PAGE'		=> $totalPages,
							  'ARCHIVED'		=> $show=="archived" ? "&archived=1" : "",
							  );
							  
	$myPage->t_body->set_var($outputPagination);	
	
		
	$myPage->setStories($myNews->getNews(0, $newsIndex, $newsPerPage));
} else {
	// don't paginate current news
	$myPage->t_body->set_var('next_button_block', '');
	$myPage->t_body->set_var('previous_button_block', '');
	$myPage->t_body->set_var('page_number_block', '');
	$myPage->setStories($myNews->getNews());
}

$myPage->showPage();
?>
