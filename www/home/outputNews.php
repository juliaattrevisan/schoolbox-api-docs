<?

require_once("news/news.php");

$this->setBlock($location, "main_stories", "stories_block");
	
$myNews = new news($topic);

//output headlines
$headlineList = $myNews->getHeadlines();

if(is_array($headlineList) )
{
	foreach($headlineList as $item)
	{
		$this->set_var("NEWS_TITLE", $item['item_heading']);
		$this->set_var("NEWS_URL", "/news.php#".$item['item_id']); 
		$this->parse("stories_block", "main_stories", true);
	}
}
else
{
	$this->set_var("stories_block", "<li>No news articles available</li>");
}

return true;
?>