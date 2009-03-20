<?php
//news output
$this->setBlock($componentHandle, 'news', 'news_block');

require_once '../library/categoryNews.php';
$newsHandler = new CategoryNews;

$newsList = $newsHandler->getAllForCategory($categoryID, $config);

if (is_array($newsList)) {

	foreach($newsList as $news) {
		if($news['owner'] == $_SESSION['id'] || $permission >= FULL_ACCESS)
			$this->setVar('MOD_NEWS', '<a href="/news/homepage/'.$news['id'].'">Modify</a>');
		else
			$this->setVar('MOD_NEWS', '');
		
		$poster = formatName($news);
		
		$this->setVar("NEWS_HEADING", scrubTEXT($news['heading']));
		$this->setVar("NEWS_BODY", scrubHTML($news['body']));
		$this->setVar("EMAIL_CONTACT", "<a href=\"/search/user/{$news['uid']}\">" . formatName($news) . "</a>");
		$this->setVar("POSTED_DATE", getRelativeTime($news['posted']));
		$this->setVar('ON_OFF', ++$j%2? '' : 'dark');
		$this->parse("news_block", "news", true);
		
	}
	
} else
	$this->setVar("news_block", "");

//setup block for submission button
$this->setBlock($componentHandle, "submit_news", "submit_news_block");
if ($permission < WRITE_ACCESS)		
	$this->setVar("submit_news_block", "");
else
	$this->parse("submit_news_block", "submit_news");	


?>