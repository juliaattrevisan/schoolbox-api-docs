<?php

$categoryHandler = new Category;
$groupHandler = new Group;
$newsHandler = new CategoryNews;
$userHandler = new User();

$this->setFile(TEMPLATE_content, "homepage/newsArchive.html");
$this->setBlock(TEMPLATE_content, "submit_news", "submit_news_block");
$this->setBlock(TEMPLATE_content, "news", "news_block");
if (!($category = $categoryHandler->getByID($this->arg[0])))
	$this->error(2);

$output = array('CATEGORY_NAME'	=> Scrub::text($category['name']),
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

    $j = 0;
	foreach($newsList as $news) {
		if($article['owner'] == $_SESSION['id'] || $groupHandler->getPermission($category['id']) >= WRITE_ACCESS)
			$this->setVar("MOD_NEWS", "<a class=\"btn modify\" href=\"/news/homepage/".$news['id']."\"><span></span>Modify</a>");
		else
			$this->setVar("MOD_NEWS", "");

		$userHandler = new User();

		$poster = $userHandler->formatName($news);

		$postedDate = new Alaress_Date_Format($news['posted'], 'U');

		$output = array(
                    "NEWS_ID"           => $news['id'],
                    "NEWS_HEADING"	=> Scrub::text($news['heading']),
                    "NEWS_BODY"		=> Scrub::html($news['body']),
                    "EMAIL_CONTACT"	=> "<a href=\"/search/user/{$news['uid']}\">$poster</a>",
                    "POSTED_DATE"	=> $postedDate->getShort(),

                    "AUTHOR" => $userHandler->formatName($news),
                    "AUTHOR_ID" => $news['uid'],
                    "AUTHOR_EXTERNAL_ID" => $news['synid'],
                    'ON_OFF' => ++$j % 2 ? '' : 'dark'
                );
		$this->setVar($output);

		$this->parse("news_block", "news", true);
	}
} else
	$this->setVar("news_block", "");



?>
