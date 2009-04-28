<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//displays news

require_once("../library/news.php");
require_once("../library/group.php");

$this->setFile(TEMPLATE_content, 'news/archive.html');
$this->setTitle('News');
$this->setTab('news');

$this->setBlock(TEMPLATE_content, 'news', 'news_block');
$this->setBlock(TEMPLATE_content, 'previous_button', 'previous_button_block');
$this->setBlock(TEMPLATE_content, 'page_number', 'page_number_block');
$this->setBlock(TEMPLATE_content, 'next_button', 'next_button_block');	
$this->setBlock(TEMPLATE_content, 'archive_button', 'archive_button_block');	

$groupHandler = new Group(NEWS_SERVICE);

//can see add news link
if(($permission = $groupHandler->getPermission(0)) >= WRITE_ACCESS)
	$this->setVar("ADD", "<b><a class=\"customise\" href=\"/news/modify\">add news</a></b>");
else
	$this->setVar("ADD", "");
	
$this->setVar('archive_button_block', '');	
	
$myNews = new News();

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
	$i = 0;
	$total = count($items);
	foreach($items as $article)
	{
		$i++;

		if(($permission >= WRITE_ACCESS && $article['item_owner'] == $_SESSION['id']) || $permission >= FULL_ACCESS)
			$this->setVar("MODIFY", "<a href=\"/news/modify/".$article['item_id']."\">modify</a>");
		else
			$this->setVar("MODIFY", "");
		
		$fullname = formatName($article);
		$owner = "<a href=\"/search/user/{$article['uid']}\">$fullname</a>";
	
		$output = array('NEWS_ID'		=> $article['item_id'],
						'NEWS_TITLE'	=> $article['item_heading'],
						'TOPIC'			=> $article['topicName'],
						'POSTER'		=> $owner,
						'DATETIME' 		=> $_SESSION['su'] == 1 ? $article['post_datetime']." expires on ".$article['expire_datetime'] : $article['post_datetime'],
						'NEWS_BODY'		=> nl2br(scrubHTML($article['item_body'])),
						'TOPIC_NAME'	=> scrubText($previous['topicName']),
						);
						
		$this->setVar($output);
		
		
		$this->parse("news_block", "news", true);		

	}
}
else
{
		$this->setVar("news_block", "No news items found");
}

?>