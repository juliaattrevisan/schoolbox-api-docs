<?

require_once("../library/news.php");

$this->setBlock($component['name'], "main_stories", "stories_block");
	
$myNews = new News();

//output headlines
$headlineList = $myNews->getHeadlines();
if(is_array($headlineList) )
{
	foreach($headlineList as $item)
	{
		$this->setVar("NEWS_TITLE", $item['item_heading']);
		$this->setVar("NEWS_URL", "/news/#".$item['item_id']);
		$this->setVar('NEWS_TIME', getRelativeTime($item['item_datetime_modified'], 0 , ''));
		$this->setVar('ON_OFF', ++$j%2? '' : 'dark'); 
		$this->parse("stories_block", "main_stories", true);
	}
}
else
{
	$this->setVar("stories_block", "<li>No news articles available</li>");
}

$this->parse('POS'.$component['position'], $component['name']);
?>