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

$this->setBlock(TEMPLATE_content, 'topic_header', 'topic_header_block');
$this->setBlock(TEMPLATE_content, 'topic', 'topic_block');
$this->setBlock('topic', 'news', 'news_block');

$this->setBlock(TEMPLATE_content, 'previous_button', 'previous_button_block');
$this->setBlock(TEMPLATE_content, 'page_number', 'page_number_block');
$this->setBlock(TEMPLATE_content, 'next_button', 'next_button_block');	
$this->setBlock(TEMPLATE_content, 'archive_button', 'archive_button_block');	

// don't paginate current news
$this->setVar('next_button_block', '');
$this->setVar('previous_button_block', '');
$this->setVar('page_number_block', '');

$this->setVar('topic_header_block', '');

$groupHandler = new Group(NEWS_SERVICE);

//can see add news link
if(($permission = $groupHandler->getPermission(0)) >= WRITE_ACCESS)
	$this->setVar("ADD", "<a class=\"customise\" href=\"/news/modify\">add news</a>");
else
	$this->setVar("ADD", "");

$this->parse('archive_button_block', 'archive_button');

$myNews = new News();
$items = $myNews->getNews();

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
						'POSTER'		=> $owner,
						'DATETIME' 		=> $_SESSION['su'] == 1 ? $article['post_datetime']." expires on ".$article['expire_datetime'] : $article['post_datetime'],
						'NEWS_BODY'		=> nl2br(scrubHTML($article['item_body'])),
						'TOPIC_NAME'	=> scrubText($previous['topicName']),
						'TOPIC_CLEAN'	=> convertToURL($previous['topicName']),
						);
						
		$this->setVar($output);
		$topicList[$article['topicName']] = 1;
		
		if ($article['topicName'] != $previous['topicName'] && $i > 1) {
			/* end topic */
			$this->parse('topic_block', 'topic', true);
			
			$this->setVar("news_block", "");
			$this->parse("news_block", "news", true);
			
			/* last rotation */
			if ($total == $i) {
				$this->setVar('TOPIC_NAME', scrubText($article['topicName']));
				$this->setVar('TOPIC_CLEAN', convertToURL($article['topicName']));
				$this->parse('topic_block', 'topic', true);
			}
			
		} elseif ($total == $i) {
			/* last rotation */
			$this->parse("news_block", "news", true);
			$this->setVar('TOPIC_NAME', scrubText($article['topicName']));
			$this->setVar('TOPIC_CLEAN', convertToURL($article['topicName']));
			
			$this->parse('topic_block', 'topic', true);
		} else {
			/* normal rotation */
			$this->parse("news_block", "news", true);		
		}
		
		$previous = $article;

	}
		
	// output tabs
	foreach ($topicList as $topic => $bin) {
		$output = array('NEWS_TOPIC'	=> scrubText($topic),
						'TOPIC_CLEAN'	=> convertToURL($topic),
					);
		$this->setVar($output);
		$this->parse('topic_header_block', 'topic_header', true);
	}
}
else
{
		$this->setVar("topic_block", "No news items found");
}

?>
