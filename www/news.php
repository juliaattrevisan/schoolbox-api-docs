<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//displays news

require_once 'include/stdfuncs.php';
if (strpos($_SERVER['REQUEST_URI'], 'news.php'))
	localRedir('/news');
	
require_once('../library/news.php');
require_once('../library/group.php');

$this->setFile(TEMPLATE_content, 'news.html');
$this->setTitle('News');
$this->setTab('news');


$this->setBlock(TEMPLATE_content, 'main_stories', 'stories_block');
$this->setBlock('main_stories', 'story_body', 'story_block');

$this->setBlock(TEMPLATE_content, 'previous_button', 'previous_button_block');
$this->setBlock(TEMPLATE_content, 'page_number', 'page_number_block');
$this->setBlock(TEMPLATE_content, 'next_button', 'next_button_block');	
$this->setBlock(TEMPLATE_content, 'archive_button', 'archive_button_block');	

// don't paginate current news
$this->setVar('next_button_block', '');
$this->setVar('previous_button_block', '');
$this->setVar('page_number_block', '');



$groupHandler = new Group(NEWS_SERVICE);

//can see add news link
if(($permission = $groupHandler->getPermission(0)) >= WRITE_ACCESS)
	$this->setVar("ADD", "<b><a class=\"customise\" href=\"/news/modify\">add news</a></b>");
else
	$this->setVar("ADD", "");

$this->parse('archive_button_block', 'archive_button');

$myNews = new News();
$items = $myNews->getNews();

if(is_array($items))
{
	foreach($items as $article)
	{
		$this->setVar("NEWS_ID",$article['item_id']); 
	 
		if(($permission >= WRITE_ACCESS && $article['item_owner'] == $_SESSION['id']) || $permission >= FULL_ACCESS)
			$this->setVar("MODIFY", "<a href=\"/news/modify/".$article['item_id']."\">modify</a>");
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
