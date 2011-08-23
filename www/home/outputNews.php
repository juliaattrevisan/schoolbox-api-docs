<?php

require_once("../library/news.php");

$this->setBlock($component['name'], "topic_header", "topic_header_block");
$this->setBlock($component['name'], "topic", "topic_block");
$this->setBlock('topic', "news", "news_block");

$myNews = new News();

$this->setVar("topic_block", "");
$this->setVar("topic_header_block", "");

//output headlines
$topicList = array();
$headlineList = $myNews->getHeadlines();
if(is_array($headlineList) )
{
	$j = $k = 0;
	$total = count($headlineList);
	foreach($headlineList as $item)
	{
		$j++;
		$output = array('NEWS_TITLE'	=> $item['item_heading'],
						'NEWS_URL'		=> '/news/#'.$item['item_id'],
						'NEWS_TIME'		=> getRelativeTime($item['item_datetime_modified'], 0 , ''),
						'ON_OFF'		=> ++$k%2? '' : 'dark',
						'NEWS_TOPIC'	=> scrubText($previous['topicName']),
						'TOPIC_CLEAN'	=> Url::convertToURL($previous['topicName']),
						);
		$this->setVar($output);
		$topicList[$item['topicName']] = 1;

		if ($item['topicName'] != $previous['topicName'] && $j > 1) {

			/* end topic */
			$this->parse('topic_block', 'topic', true);

			$k = 0;
			$this->setVar('ON_OFF', ++$k%2? '' : 'dark');

			$this->setVar("news_block", "");
			$this->parse("news_block", "news", true);

			/* last rotation */
			if ($total == $j) {
				$this->setVar('NEWS_TOPIC', scrubText($item['topicName']));
				$this->setVar('TOPIC_CLEAN', Url::convertToURL($item['topicName']));

				$this->parse('topic_block', 'topic', true);
			}

		} elseif ($total == $j) {
			/* last rotation */
			$this->parse("news_block", "news", true);

			$this->parse('topic_block', 'topic', true);
		} else {
			/* normal rotation */
			$this->parse("news_block", "news", true);
		}

		$previous = $item;


	}
}
else
{
	$this->setVar("topic_block", "<li>No news articles available</li>");
}

// output tabs
foreach ($topicList as $topic => $bin) {
	$output = array('NEWS_TOPIC'	=> scrubText($topic),
				    'TOPIC_CLEAN'	=> Url::convertToURL($topic),
					);
	$this->setVar($output);
	$this->parse('topic_header_block', 'topic_header', true);
}

$this->parse('POS'.$component['position'], $component['name']);
?>