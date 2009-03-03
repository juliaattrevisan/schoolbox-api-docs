<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/categoryNews.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

$categoryHandler = new Category;
$groupHandler = new Group;
$newsHandler = new CategoryNews;

$this->setFile(TEMPLATE_content, "homepage/newsArchive.html");
$this->setBlock(TEMPLATE_content, "submit_news", "submit_news_block");
$this->setBlock(TEMPLATE_content, "news", "news_block");
if (!($category = $categoryHandler->getByID($this->arg[0])))
	$this->error(2);

$output = array('CATEGORY_NAME'	=> scrubText($category['name']),
				'TITLE'			=> 'News Archive',
				'ID'			=> $category['id'],
				);
$this->setVar($output);

//hide add button
if($groupHandler->getPermission($category['id']) >= WRITE_ACCESS)
	$this->parse("submit_news_block", "submit_news");
else
	$this->setVar("submit_news_block", "");

	
$newsList = $newsHandler->getAllForCategory($category['id'], false, true);

if(is_array($newsList)) {
	foreach($newsList as $news) {
		if($article['owner'] == $_SESSION['id'] || $groupHandler->getPermission($category['id']) >= WRITE_ACCESS)
			$this->setVar("MOD_NEWS", "<a href=\"/news/homepage/".$news['id']."\">Modify</a>");
		else
			$this->setVar("MOD_NEWS", "");
		
		$poster = formatName($news);
		
		$output = array("NEWS_HEADING"	=> scrubTEXT($news['heading']),
						"NEWS_BODY"		=> scrubHTML($news['body']),
						"EMAIL_CONTACT"	=> "<a href=\"/search/user/{$news['uid']}\">$poster</a>",
						"POSTED_DATE"	=> outputDate(DATE_short_format, $news['posted']),
						);
		$this->setVar($output);
		
		$this->parse("news_block", "news", true);
	}
} else
	$this->setVar("news_block", "");


	
?>