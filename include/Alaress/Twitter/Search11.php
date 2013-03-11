<?php
/**
 * Alaress_Twitter_Search11
 *
 * Just like Alaress_Twitter_Search, but for the 1.1 API.
 *
 * You cannot use the Search API to find Tweets older than about a week.
 */
class Alaress_Twitter_Search11 extends Alaress_Twitter_Search {
	private $searchPrefix = 'https://api.twitter.com/1.1/search/tweets.json?q=';
	private $user = '';
	private $searchUrl = '';
	private $keywords = '';
	private $tweets = array();
	private $maxTweets = 20;
	private $filter = null;


	/**
	 * Generate query string params (this function generates them as an array)
	 *
	 * @return string
	 */
	protected function generateParameters() {
		$search = '';
		if (strlen($this->getUser())) {
			$search .= "from:{$this->getUser()} ";
		}

		//
		// No idea how hashtags should work. #tag1 #tag2?
		if (strlen($this->getKeywords())) {
			$search .= '#'.implode(' #', preg_split('/\W+/', $this->getKeywords()));
		}
		return array('q' => trim($search));
	}

	/**
	 * Load tweets
	 */
	public function load() {
		$processed = array();
		$resource  = 'https://api.twitter.com/1.1/search/tweets.json';
		$params    = $this->generateParameters();

		if (!empty($params)) {
			$cache = new Alaress_Cache_Callable(
				array(
					new Alaress_Twitter_Loader_OAuth(
						Cfg::get('twitter_api_consumer_key'),
						Cfg::get('twitter_api_consumer_secret'),
						Cfg::get('twitter_api_oauth_token'),
						Cfg::get('twitter_api_oauth_token_secret')
					),
					'load'
				),
				array($resource, $params,)
			);
			$all_tweets = $cache->load();

            if ($this->getMaxTweets() && is_numeric($this->getMaxTweets()) && is_array($all_tweets)) {
				$all_tweets = array_slice($all_tweets, 0, $this->getMaxTweets());
			}

			foreach ($all_tweets as $result) {
				$processed[] = array(
					'text'   => $this->getFilter()->filterText($result->text),
					'date'   => $result->created_at,
					'author' => $result->user->screen_name,
					'avatar' => $result->user->profile_image_url,
				);
			}
		}

		$this->setTweets($processed);
	}
}
