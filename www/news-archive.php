<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//displays news

require_once("include/permission.php");
require_once("news/news.php");

$this->setFile(TEMPLATE_content, 'news.html');
$this->setTitle('News');

$this->setBlock(TEMPLATE_content, "main_stories", "stories_block");
$this->setBlock("main_stories", "story_body", "story_block");

$this->setBlock(TEMPLATE_content, 'previous_button', 'previous_button_block');
$this->setBlock(TEMPLATE_content, 'page_number', 'page_number_block');
$this->setBlock(TEMPLATE_content, 'next_button', 'next_button_block');	
$this->setBlock(TEMPLATE_content, 'archive_button', 'archive_button_block');	

//can see add news link
if(isStaff($_SESSION['gid']))
	$this->setVar("ADD", "<b><a href=\"/news/modnews.php\">add news</a></b>");
else
	$this->setVar("ADD", "");
	
$this->setVar('archive_button_block', '');	
	
$myNews = new news('archived');

$pageNum = $this->arg[0];


// output pagination
$newsList = $myNews->getNews(0, true);

if(!$pageNum)
	$pageNum = 1;
	
$newsPerPage = 15;
$totalPages = ceil(count($newsList)/$newsPerPage);
$newsIndex = $newsPerPage * ($pageNum-1);		

if($pageNum<2)	
	$this->setVar('previous_button_block', '');
else
	$this->parse('previous_button_block', 'previous_button');

if($pageNum >= $totalPages)
	$this->setVar('next_button_block', '');
else
	$this->parse('next_button_block', 'next_button');

if($totalPages > 1) {
	for($j=1; $j <= $totalPages; $j++) {
		if($pageNum == $j)
			 $this->setVar('CURRENT', ' current');
		else
			 $this->setVar('CURRENT', '');
			 
		$this->setVar('PAGE_INDEX', $j);
		$this->parse('page_number_block','page_number', true); 
	}
}
else
	$this->setVar('page_number_block','');

$outputPagination = array('PREVIOUS_PAGE' 	=> $pageNum - 1,
						  'NEXT_PAGE'		=> $pageNum + 1,
						  'LAST_PAGE'		=> $totalPages,
						  'ARCHIVED'		=> $show=="archived" ? "&archived=1" : "",
						  );
						  
$this->setVar($outputPagination);	

	
$items = $myNews->getNews(0, true, $newsIndex, $newsPerPage);

if(is_array($items))
{
	foreach($items as $article)
	{
		$this->setVar("NEWS_ID",$article['item_id']); 
	 
		if($article['item_owner'] == $_SESSION['id'] || $_SESSION['su'] == 1)
			$this->setVar("MODIFY", "<a href=\"/news/modnews.php?id=".$article['item_id']."\">modify</a>");
		else
			$this->setVar("MODIFY", "");
		
		$this->setVar("NEWS_TITLE", $article['item_heading']);
		
		$fullname = formatName($article);
		$owner = "<a href=\"/search/user/{$article['uid']}\">$fullname</a>";

		$this->setVar("POSTER", $owner);
		
		if($_SESSION['su'] == 1)
			$this->setVar("DATETIME", $article['post_datetime']." expires on ".$article['expire_datetime']);
		else
			$this->setVar("DATETIME", $article['post_datetime']);
			
		$this->setVar("NEWS_BODY", nl2br(scrubHTML($article['item_body'])));
		
		$this->parse("story_block", "story_body");
		$this->parse("stories_block", "main_stories", true);

	}
}
else
{
		$this->setVar("stories_block", "No news items found");
}

?>