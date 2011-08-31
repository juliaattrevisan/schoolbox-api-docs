<?php
//news output
$this->setBlock($componentHandle, 'news', 'news_block');

$newsHandler = new CategoryNews;

$newsList = $newsHandler->getAllForCategory($categoryID, $config);

if (is_array($newsList)) {
	$userHandler = new User();

	foreach($newsList as $news) {
		if($news['owner'] == $_SESSION['id'] || $permission >= FULL_ACCESS || (NEWS_teacher_edit_other && $permission >= WRITE_ACCESS)) {
			$this->setVar('MOD_NEWS', '<a href="/news/homepage/'.$news['id'].'">Modify</a>');
		}
		else {
			$this->setVar('MOD_NEWS', '');
		}

		$poster = $userHandler->formatName($news);
		$postedTime = new Alaress_Date_Relative($news['posted']);

        $this->setVar(array(
			"NEWS_ID" => $news['id'],
			"NEWS_HEADING" => scrubTEXT($news['heading']),
			"NEWS_BODY" => scrubHTML($news['body']),
			"EMAIL_CONTACT" => "<a href=\"/search/user/{$news['uid']}\">" . $userHandler->formatName($news) . "</a>",
			"POSTED_DATE" => $postedTime->getRelativeTime(),
			'ON_OFF' => ++$j%2? '' : 'dark'
		));

		$this->parse("news_block", "news", true);
	}
}
else {
	$this->setVar("news_block", "");
}

//setup block for submission button
$this->setBlock($componentHandle, "submit_news", "submit_news_block");

if ($permission < WRITE_ACCESS) {
	$this->setVar("submit_news_block", "");
}
else {
	$this->parse("submit_news_block", "submit_news");
}