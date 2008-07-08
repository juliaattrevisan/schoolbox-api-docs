<?php
//news output
$this->setBlock($componentHandle, "news", "news_block");

require_once '../library/categoryNews.php';
$newsHandler = new CategoryNews;

$newsList = $newsHandler->getAllForCategory($categoryID);

if (is_array($newsList)) {

	foreach($newsList as $news) {
		if($news['owner'] == $_SESSION['id'] || $_SESSION['su'] == 1 || CATEGORY_perms == 2)
			$this->setVar("MOD_NEWS", "<a href=\"/subject/modSubNews.php?modify=".$news['id']."\">Modify</a>");
		else
			$this->setVar("MOD_NEWS", "");
		
		$poster = formatName($news);
		
		$this->setVar("NEWS_HEADING", scrubTEXT($news['heading']));
		$this->setVar("NEWS_BODY", scrubHTML($news['body']));
		$this->setVar("EMAIL_CONTACT", "<a href=\"/search/user/{$news['uid']}\">" . formatName($news) . "</a>");
		$this->setVar("POSTED_DATE", date(DATE_short_format, $news['posted']));
		$this->parse("news_block", "news", true);
	}
	
} else
	$this->setVar("news_block", "");

//setup block for submission button
$this->setBlock($componentHandle, "submit_news", "submit_news_block");
if(CATEGORY_perms < 2)		
	$this->setVar("submit_news_block", "");
else
	$this->parse("submit_news_block", "submit_news");	


?>