<?php
/**
 * ${NAME}
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */

/* Put into config serialized array
 */
$this->setBlock($componentHandle, 'tweet', 'tweet_block');

$allTweets = new Alaress_Twitter_Stream($config['twitterusername']);
$allTweets->setMaxTweets($config['twitternum'])->load();

foreach($allTweets->getTweets() as $currentTweet) {
	$this->setVar(array(
		'TWEET_TEXT' => $currentTweet['text'],
		'TWEET_DATE' => $currentTweet['date'],
	));

	$this->parse('tweet_block', 'tweet', true);
}