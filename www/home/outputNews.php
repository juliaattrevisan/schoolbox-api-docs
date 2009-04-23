<?

require_once("../library/news.php");

$this->setBlock($component['name'], "topic", "topic_block");
$this->setBlock('topic', "news", "news_block");
	
$myNews = new News();

//output headlines
$headlineList = $myNews->getHeadlines();
if(is_array($headlineList) )
{
	$total = count($headlineList);
	foreach($headlineList as $item)
	{
		$output = array('NEWS_TITLE'	=> $item['item_heading'],
						'NEWS_URL'		=> '/news/#'.$item['item_id'],
						'NEWS_TIME'		=> getRelativeTime($item['item_datetime_modified'], 0 , ''),
						'ON_OFF'		=> ++$j%2? '' : 'dark',
						'NEWS_TOPIC'	=> scrubText($previous['topicName']),
						);
		$this->setVar($output);

		if ($item['topicName'] != $previous['topicName'] && $j > 1) {
			/* end topic */
			$this->parse('topic_block', 'topic', true);
			
			$this->setVar("news_block", "");
			$this->parse("news_block", "news", true);
			
			/* last rotation */
			if ($total == $j) {
				$this->setVar('NEWS_TOPIC', scrubText($item['topicName']));
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
	$this->setVar("stories_block", "<li>No news articles available</li>");
}

$this->parse('POS'.$component['position'], $component['name']);
?>