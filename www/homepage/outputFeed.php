<?php
//output feed
$this->setBlock($componentHandle, 'feed', 'feed_block');

require_once SITE_root.'/library/feed.php';
$feedHandler = new Feed;

$db = DeprecatedDatabase::getInstance();
$token = $db->getOne("SELECT `key` FROM `user` WHERE `id` = '{$_SESSION['id']}'");
$this->setVar('USER_TOKEN', $token);

$feedList = $feedHandler->getFeed($categoryID, $config['size']);

if (count($feedList) > 0) {
	foreach ($feedList as $feed) {
		$feedTime = new Alaress_Date_Relative($feed['date']);
		$feedTime->setPrefix('');

		$this->setVar(array(
			'FEED_MESSAGE' => $feed['messageHTML'],
			'FEED_DATE' => $feedTime->getRelativeTime(),
			'ON_OFF' => ++$j%2? '' : 'dark'
		));

		$this->parse('feed_block', 'feed', true);
	}
}
else {
	$this->setVar('feed_block', '');
}
