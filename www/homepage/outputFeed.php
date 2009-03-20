<?php
//output feed
$this->setBlock($componentHandle, 'feed', 'feed_block');

require_once '../library/feed.php';
$feedHandler = new Feed;

$feedList = $feedHandler->getFeed($categoryID, $config['size']);

if (is_array($feedList)) {
	foreach ($feedList as $feed) {
		$this->setVar('FEED_MESSAGE', $feed['messageHTML']);
		$this->setVar('ON_OFF', ++$j%2? '' : 'dark');
		$this->parse('feed_block', 'feed', true);
	}

} else
	$this->setVar('feed_block', '');

?>