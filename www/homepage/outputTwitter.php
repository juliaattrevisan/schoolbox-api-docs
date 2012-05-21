<?php
/**
 * outputTwitter.php
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */

/* Put into config serialized array
 */
$this->setBlock($componentHandle, 'tweet', 'tweet_block');

$allTweets = new Alaress_Twitter_Search($config['username'], $config['keywords']);
$allTweets->setMaxTweets($config['size'])->load();

$allResults = $allTweets->getTweets();

/* Render out tweets
 */
$this->setVar('tweet_block', '');

if (count($allResults)) {
	foreach($allResults as $currentTweet) {
		if ($i%2) {
			$color = '';
		}
		else {
			$color = 'dark';
		}

		$date = new Alaress_Date_Format(strtotime($currentTweet['date']), 'U');

		$this->setVar(array(
			'TWEET_TEXT' => $currentTweet['text'],
			'TWEET_DATE' => $date->getShort(),
			'TWEET_AUTHOR' => $currentTweet['author'],
			'TWEET_AVATAR' => $currentTweet['avatar'],
			'ON_OFF' => $color,
		));

		$this->parse('tweet_block', 'tweet', true);

		$i++;
	}
}