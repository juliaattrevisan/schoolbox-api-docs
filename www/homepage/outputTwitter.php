<?php
/**
 * ${NAME}
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */

$username = 'alaress';

/* Put into config serialized array
 */
$this->setBlock($componentHandle, 'tweet', 'tweet_block');
$this->setBlock('config', 'twitterfilter', 'twitterfilter_block');

/* These are the allowable resource calls in Twitter

 * 'name' is used for presentation in the dropdown on the homepage component
 * 'method' is used in the db homepage_comp_location's config column
 * 'resource' is the url used by the api
 */
$allResourceMethods = array(
	array(
		'name' => 'Default',
		'method' => 'default',
		'resource' => 'statuses/user_timeline'
	),
	array(
		'name' => 'Mentions',
		'method' => 'mentions',
		'resource' => 'statuses/mentions'
	),
	array(
		'name' => 'Favorites',
		'method' => 'favourites',
		'resource' => 'favorites'
	),
	array(
		'name' => 'Retweets By Me',
		'method' => 'retweets_by_me',
		'resource' => 'statuses/retweeted_by_me'
	),
	array(
		'name' => 'Retweets To Me',
		'method' => 'retweets_to_me',
		'resource' => 'statuses/retweeted_to_me'
	),
	array(
		'name' => 'Retweets of Me',
		'method' => 'retweets_of_me',
		'resource' => 'statuses/retweeted_of_me'
	),
);

/* Preselect chosen method
 */
$resource = '';

foreach ($allResourceMethods as $currentResourceMethod) {

	/* Remember the resource url for the selected method, we use it
	 * when gathering tweets
	 */
	if ($currentResourceMethod['method'] == $config['twitterfilter']) {
		$selected = 'selected="selected"';
		$resource = $currentResourceMethod['resource'];
	}
	else {
		$selected = '';
	}

	$this->setVar(array(
		'TWITTERFILTER_NAME' => $currentResourceMethod['name'],
		'TWITTERFILTER_METHOD' => $currentResourceMethod['method'],
		'TWITTERFILTER_SELECTED' => $selected,
	));

	$this->parse('twitterfilter_block', 'twitterfilter', true);
}

/* Start gethering tweets
 */

/* Do a special regex rendering as the Twitter Search API searches all
 * tweets everywhere, not this user's twitter feed.
 *
 * Filter it all manually.
 */
if (strlen(trim($config['twitterkeyword']))) {
	$maxLimit = 100;

	/* Pass a special filter in which can detect traces of #hashtags
	 */
	$allTweets = new Alaress_Twitter_Stream($username, new Alaress_Twitter_Filter_Search($config['twitterkeyword']));
	$allTweets->getLoader()->setResource($resource);
	$allTweets->setMaxTweets($maxLimit)->load();

	$allResults = $allTweets->getTweets();

	/* Render out tweets
	 */
	$this->setVar('tweet_block', '');

	if (count($allResults)) {
		for ($i = 0, $j = 0; ($i < $maxLimit) && ($j < $config['twittermaxnum']); $i++) {
			$currentTweet = $allResults[$i];

			/* If the filter returned null for currentTweet['text'] then the keyword
			 * was not found; skip rendering this tweet
			 */
			if ($currentTweet['text'] !== null) {
				$this->setVar(array(
					'TWEET_TEXT' => $currentTweet['text'],
					'TWEET_DATE' => $currentTweet['date'],
				));

				$this->parse('tweet_block', 'tweet', true);

				$j++;
			}
		}
	}
}
/* Normal twitter results
 */
else {
	$allTweets = new Alaress_Twitter_Stream($username);
	$allTweets->getLoader()->setResource($resource);
	$allTweets->setMaxTweets($config['twittermaxnum'])->load();

	$allResults = $allTweets->getTweets();

	/* Render out tweets
	 */
	$this->setVar('tweet_block', '');

	if (count($allResults)) {
		foreach($allResults as $currentTweet) {
			$this->setVar(array(
				'TWEET_TEXT' => $currentTweet['text'],
				'TWEET_DATE' => $currentTweet['date'],
                'ON_OFF' => $i++%2 ? 'dark' : '',
			));

			$this->parse('tweet_block', 'tweet', true);
		}
	}
}

