<?php
/**
 * Created by Steve Chan.
 *
 * User: Steve
 * Date: 28/07/11
 * Time: 12:25 PM
 *
 * Twitter_Loader_Basic
 *
 * This loads tweets via the generic RSS feed
 *
 * This class encapsulates the tweet loading methodology, returning a key/value
 * array for text (the contents of the tweet) and the date (the date the tweet
 * was published)
 */
class Alaress_Twitter_Loader_Basic implements Alaress_Twitter_Loader_Interface {
	private $source;

	/**
	 * Set source file from which to read tweets
	 *
	 * @param  $user
	 */
	public function __construct($user) {
		$this->setSource($user);
	}

	/**
	 * Load results. Preprocess them to get rid of unwanted data and to format
	 * the return value in a uniform structure.
	 *
	 * @return SimpleXMLElement
	 */
	public function load() {
		$allTweets = simplexml_load_file($this->getSource());

		$preprocessed = array();

		/* Iterate to set up for Filters and get rid of unwanted stuff
		 */
		for ($i = 0; $i < count($allTweets->channel->item); $i++) {
			$preprocessed[] = array(
				'text' => $allTweets->channel->item[$i]->description,
				'date' => $allTweets->channel->item[$i]->pubDate,
			);
		}

		return $preprocessed;
	}

	/**
	 * Set source file from which to get tweets
	 *
	 * @param  $source
	 * @return void
	 */
	private function setSource($user) {
		$this->source = 'http://twitter.com/statuses/user_timeline/' . $user . '.rss';
	}

	/**
	 * Get the source file from which to get tweets
	 *
	 * @return
	 */
	private function getSource() {
		return $this->source;
	}
}
