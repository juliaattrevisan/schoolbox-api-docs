<?php
/**
 * Created by Steve Chan.
 *
 * User: Steve
 * Date: 28/07/11
 * Time: 12:25 PM
 *
 * Twitter_Loader_OAuth
 *
 * This loads tweets via the OAuth interface that Twitter brings in on August 31
 * 2011. It encapsulates @abraham's twitteroauth class library found at:
 * https://github.com/abraham/twitteroauth/blob/master/DOCUMENTATION
 *
 * This class encapsulates the tweet loading methodology, returning a key/value
 * array for text (the contents of the tweet) and the date (the date the tweet
 * was published)
 */
class Alaress_Twitter_Loader_OAuth implements Alaress_Twitter_Loader_Interface {

	/**
	 * Load required classes, instantiate a new connection using the four
	 * variables we obtained by running connect.php
	 */
	public function __construct() {
		require_once('oauth/config.php');
		require_once('oauth/twitteroauth.php');

		$this->setConnection(new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, OAUTH_TOKEN, OAUTH_TOKEN_SECRET));
	}

	/**
	 * Load results. Preprocess them to get rid of unwanted data and to format
	 * the return value in a uniform structure.
	 *
	 * @return array
	 */
	public function load() {
		$allTweets = $this->getConnection()->get('statuses/user_timeline');

		$preprocessed = array();

		/* Iterate to set up for Filters and get rid of unwanted stuff
		 */
		foreach($allTweets as $currentTweet) {
			$preprocessed[] = array(
				'text' => $currentTweet->text,
				'date' => $currentTweet->created_at,
			);
		}

		return $preprocessed;
	}

	/**
	 * Set an OAuth connection object
	 *
	 * @param  $connection
	 * @return void
	 */
	private function setConnection($connection) {
		$this->connection = $connection;
	}

	/**
	 * Get an OAuth connection object
	 *
	 * @return
	 */
	private function getConnection() {
		return $this->connection;
	}
}
