<?php
/**
 * Alaress_Twitter_Search
 *
 * Wraps up the Twitter Search API which is a get-only request, unlike
 * the rest of the Alaress_Twitter module which would be capable of full
 * REST operations.
 *
 * Notes from https://dev.twitter.com/docs/using-search:
 * The Search API is not complete index of all Tweets, but instead an index
 * of recent Tweets. At the moment that index includes between 6-9 days of Tweets.
 *
 * You cannot use the Search API to find Tweets older than about a week.
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Alaress_Twitter_Search {
	private $searchPrefix = 'http://search.twitter.com/search.json?&q=';
	private $user = '';
	private $searchUrl = '';
	private $keywords = '';
	private $tweets = array();
	private $maxTweets = 20;
	private $filter = null;

	/**
	 * Constructor
	 *
	 * @param $user
	 * @param $keywords
	 * @param null $filter
	 */
	public function __construct($user, $keywords, $filter = null) {
		$this->setUser(trim($user));
		$this->setKeywords(trim($keywords));

		/* Filter object for tweet content. Activates urls, etc. or you can pass
		 * or own custom instance.
		 */
		if ($filter === null) {
			$this->setFilter(new Alaress_Twitter_Filter_Search());
		}
		else {
			$this->setFilter($filter);
		}
	}

	/**
	 * Generate query string params
	 *
	 * @return string
	 */
	protected function generateParameters() {
		$params = '';

		/* Construct query parameters for tweet authors
		 */
		if (strlen($this->getUser())) {
			$params .= "from:{$this->getUser()} ";
		}

		if (strlen($this->getKeywords())) {
			$params .= $this->getKeywords();
		}

		return urlencode(trim($params));
	}

	/**
	 * Load tweets
	 */
	public function load() {
		$processed = array();
		$params = $this->generateParameters();

		if (strlen(trim($params))) {
			$this->setSearchUrl($this->getSearchPrefix() . $params);

			/* Instantiate a cache and load from it
			 */
			$cache = new Alaress_Twitter_Cache($this->getUser(), $this->getKeywords(), $this->getSearchUrl());

			$allTweets = json_decode($cache->load());

			/* Slice the required number of elements out
			 * Otherwise return all tweets when a limit is not specified (ie blank)
			 */
            if ($this->getMaxTweets() && is_numeric($this->getMaxTweets()) && is_array($allTweets->results)) {
                $allResults = array_slice($allTweets->results, 0, $this->getMaxTweets());
			}
			else {
				$allResults = $allTweets->results;
			}

			/* Iterate and process
			 */
			for ($i = 0; $i < count($allResults); $i++) {
				$processed[] = array(
					'text' => $this->getFilter()->filterText($allResults[$i]->text),
					'date' => $allResults[$i]->created_at,
					'author' => $allResults[$i]->from_user,
					'avatar' => $allResults[$i]->profile_image_url,
				);
			}
		}

		$this->setTweets($processed);
	}

	/* Getters/Setters
	 */

	/**
	 * Get tweets
	 *
	 * @return array
	 */
	public function getTweets() {
		return $this->tweets;
	}

	/**
	 * Set tweets
	 *
	 * @param $tweets
	 */
	protected function setTweets($tweets) {
		$this->tweets = $tweets;
	}

	/**
	 * Set keywords
	 *
	 * @param $keywords
	 */
	public function setKeywords($keywords) {
		$this->keywords = $keywords;
	}

	/**
	 * Get keywords
	 *
	 * @return string
	 */
	protected function getKeywords() {
		return $this->keywords;
	}

	/**
	 * Set search url
	 *
	 * @param $searchUrl
	 */
	protected function setSearchUrl($searchUrl) {
		$this->searchUrl = $searchUrl;
	}

	/**
	 * Get search url
	 *
	 * @return string
	 */
	protected function getSearchUrl() {
		return $this->searchUrl;
	}

	/**
	 * Set user
	 *
	 * @param $user
	 */
	public function setUser($user) {
		$this->user = $user;
	}

	/**
	 * Get user
	 *
	 * @return string
	 */
	protected function getUser() {
		return $this->user;
	}

	/**
	 * Set the max number of tweets to preprocess
	 *
	 * Chainable method
	 *
	 * @param  $maxTweets
	 * @return self
	 */
	public function setMaxTweets($maxTweets) {
		$this->maxTweets = $maxTweets;

		return $this;
	}

	/**
	 * Get the max number of tweets to preprocess
	 *
	 * @return int
	 */
	protected function getMaxTweets() {
		return $this->maxTweets;
	}

	/**
	 * Get search prefix
	 *
	 * @return string
	 */
	protected function getSearchPrefix() {
		return $this->searchPrefix;
	}

	/**
	 * Set filter
	 *
	 * @param $filter
	 */
	protected function setFilter($filter) {
		$this->filter = $filter;
	}

	/**
	 * Get filter
	 *
	 * @return null
	 */
	protected function getFilter() {
		return $this->filter;
	}
}
