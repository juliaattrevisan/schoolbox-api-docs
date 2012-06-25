<?php
$this->setBlock($componentHandle, 'news', 'news_block');
$this->setBlock($componentHandle, "submit_news", "submit_news_block");

/* Set up component name
 */
$this->setVar(array(
	'COMPONENT_NAME' => strlen(trim($config['componentname'])) ? $config['componentname'] : 'News',

	/* These are for the Select Alternate Source resourcebrowser
	 */
	'COMPONENT_CONFIG_SRC_ID' => $config['homepage-news'],
	'COMPONENT_CONFIG_SRC_NAME' => $src['name'],
	'COMPONENT_HANDLE' => strtolower($componentHandle)
));

/* Prevent multiple news from other categories stacking up in the wrong place
 * and initialise empty news roll by default
 */
$this->setVar('news_block', '');

$newsHandler = new CategoryNews();

/* Select news feed from alternate source, if specified
 */
$alternateCategoryId = false;
$alternatePermissions = 0;

/* If you chose an alternate source, check the permissions on it
 */
if (strlen($config['homepage-news'])) {
	$matches = array();
	preg_match('#/homepage/(\d+)#', $config['homepage-news'], $matches);

	if (count($matches)) {
		$alternatePermissions = $groupHandler->getPermission($matches[1]);

		if ($alternatePermissions >= READ_ACCESS) {
			$alternateCategoryId = $matches[1];
		}
	}
}

/* Load news from some other homepage
 */
if ($alternateCategoryId) {
	$newsList = $newsHandler->getAllForCategory($alternateCategoryId, $config);

	/* Controls such as Post News
	 */
	if ($alternatePermissions < WRITE_ACCESS) {
		$this->setVar("submit_news_block", "");
	}
	else {
		$this->setVar(array(
			'NEWS_CATEGORY_ID' => $alternateCategoryId
		));

		$this->parse("submit_news_block", "submit_news");
	}
}
/* Tried to load from alternate source but failed
 */
else if (strlen($config['homepage-news']) && !$alternateCategoryId){
	$newsList = array();

	/* Have update form to back to this homepage, not the wiki's homepage
	 */
	$this->setVar(array(
		'NEWS_CATEGORY_ID' => $categoryID
	));

	$this->setVar("submit_news_block", "");
}
/* Load news for the current homepage
 */
else if (!strlen($config['homepage-news'])) {
	$newsList = $newsHandler->getAllForCategory($categoryID, $config);

	/* Have update form to back to this homepage, not the wiki's homepage
	 */
	$this->setVar(array(
		'NEWS_CATEGORY_ID' => $categoryID
	));

	/* Controls such as Post News
	 */
	if ($permission < WRITE_ACCESS) {
		$this->setVar("submit_news_block", "");
	}
	else {
		$this->parse("submit_news_block", "submit_news");
	}
}
/* Default
 */
else {
	$newsList = array();

	$this->setVar("submit_news_block", "");
}

/* Iterate
 */
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
			"NEWS_HEADING" => Scrub::text($news['heading']),
			"NEWS_BODY" => Scrub::html($news['body']),
			"EMAIL_CONTACT" => "<a href=\"/search/user/{$news['uid']}\">" . $userHandler->formatName($news) . "</a>",
			"POSTED_DATE" => $postedTime->getRelativeTime(),
			'ON_OFF' => ++$j%2? '' : 'dark'
		));

		$this->parse("news_block", "news", true);
	}
}