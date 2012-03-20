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
		$url = new Url($previous['topicName']);

		$newsTime = new Alaress_Date_Relative($item['item_datetime_modified']);
		$newsTime->setPrefix('');

		$output = array('NEWS_TITLE'	=> $item['item_heading'],
						'NEWS_URL'		=> '/news/item/'.$item['item_id'],
						'NEWS_TIME'		=> $newsTime->getRelativeTime(),
						'ON_OFF'		=> ++$k % 2 ? '' : 'dark',
						'NEWS_TOPIC'	=> Scrub::text($previous['topicName']),
						'TOPIC_CLEAN'	=> $url->getSlug(),
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
				$url = new Url($item['topicName']);

				$this->setVar('NEWS_TOPIC', Scrub::text($item['topicName']));
				$this->setVar('TOPIC_CLEAN', $url->getSlug());

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
	$url = new Url($topic);

	$output = array('NEWS_TOPIC'	=> Scrub::text($topic),
				    'TOPIC_CLEAN'	=> $url->getSlug(),
					);
	$this->setVar($output);
	$this->parse('topic_header_block', 'topic_header', true);
}

$this->parse('POS'.$component['position'], $component['name']);
?>