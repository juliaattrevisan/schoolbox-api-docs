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
		$params = '';
		if (strlen($this->getUser())) {
			$params .= "from:{$this->getUser()} ";
		}

		//
		// No idea how hashtags should work. #tag1 #tag2?
		if (strlen($this->getKeywords())) {
			$params .= '#'.implode(' #', preg_split('/\W+/', $this->getKeywords()));
		}

		return array('q' => trim($params));
	}

	/**
	 * Load tweets
	 */
	public function load() {
		$processed = array();
		$resource = 'https://api.twitter.com/1.1/search/tweets.json';
		$params = $this->generateParameters();

		if (!empty($params)) {
			$twitter_loader = new Alaress_Twitter_Loader_OAuth();
			$twitter_loader->setResource($resource);
			$twitter_loader->setParams($params);
			$processed = $twitter_loader->load();
//			foreach ($twitter_loader->load() as $result) {
//				var_dump($result);
//				$processed[] = array(
//					'text' => $this->getFilter()->filterText($result->text),
//					'date' => $result->created_at,
//					'author' => $result->user->screen_name,
//					'avatar' => $result->user->profile_image_url,
//				);
//
//			}
//			var_dump($twitter_loader->load());
//			$this->setSearchUrl($this->getSearchPrefix() . $params);

			/* Instantiate a cache and load from it
			 */
//			$cache = new Alaress_Twitter_Cache($this->getUser(), $this->getKeywords(), $this->getSearchUrl());
//
//			$allTweets = json_decode($cache->load());
//
//			/* Slice the required number of elements out
//			 * Otherwise return all tweets when a limit is not specified (ie blank)
//			 */
//            if ($this->getMaxTweets() && is_numeric($this->getMaxTweets()) && is_array($allTweets->results)) {
//                $allResults = array_slice($allTweets->results, 0, $this->getMaxTweets());
//			}
//			else {
//				$allResults = $allTweets->results;
//			}
//
//			/* Iterate and process
//			 */
//			for ($i = 0; $i < count($allResults); $i++) {
//				$processed[] = array(
//					'text' => $this->getFilter()->filterText($allResults[$i]->text),
//					'date' => $allResults[$i]->created_at,
//					'author' => $allResults[$i]->from_user,
//					'avatar' => $allResults[$i]->profile_image_url,
//				);
//			}
		}

		$this->setTweets($processed);
	}
}
