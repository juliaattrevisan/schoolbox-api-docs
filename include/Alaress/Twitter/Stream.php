<?php
/**
 * Created by Steve Chan.
 * First introduced by Leonard.
 *
 * User: Steve
 * Date: 19/07/11
 * Time: 1:29 PM
 *
 * Twitter Feed Preprocessor
 *
 * Loads a bunch of tweets for a prescribed user, ready for display on a site.
 * You can vary the Filter class being used, and also the Loader class (if
 * Twitter changes the way people accesses tweets)
 */
class Alaress_Twitter_Stream {

	/* Data filtration class
	 */
	private $filter = null;

	/* Feed settings.
	 * By default, Twitter only shits out 20 via the RSS subscription.
	 */
	private $tweets;
	private $maxTweets = 20;

	/* Loader (Strategy)
	 * Change strategies here (this is the name of the loader class)
	 */
	private $loader = 'Twitter_Loader_OAuth';

	/**
	 * Preprocess tweets, ready for output
	 *
	 * @param $user
	 * @param null $filter
	 *
	 * @internal param $source
	 */
	public function __construct($user, $filter = null) {

		/* Filter object for tweet content. Activates urls, etc. or you can pass
		 * or own custom instance.
		 */
		if ($filter === null) {
			$this->setFilter(new Alaress_Twitter_Filter_Standard());

			/* Set user name
			 */
			$this->getFilter()->setUser($user);
		}
		else {
			$this->setFilter($filter);
		}

		/* Loading mechanism selection (Strategy)
		 */
		switch ($this->getLoader()) {
			case 'Twitter_Loader_OAuth':
				$loader = new Alaress_Twitter_Loader_OAuth();
				break;

			default:
				$loader = new Alaress_Twitter_Loader_Basic($user);
				break;
		}

		$this->setLoader($loader);
	}

	/**
	 * Load and preprocess tweets using the selected Loader class, store result
	 *
	 * Chainable method
	 *
	 * @return array
	 */
	public function load() {
		$allTweets = $this->getLoader()->load();

		$processed = array();

		/* Process and keep output
		 */
		for ($i = 0; ($i < $this->getMaxTweets()) && ($i < count($allTweets)); $i++) {
			$processed[] = array(
				'text' => $this->getFilter()->filterText($allTweets[$i]['text']),
				'date' => $this->getFilter()->filterDate($allTweets[$i]['date']),
			);
		}

		$this->setTweets($processed);

		return $this;
	}

	/**
	 * Shit out ajax format (json) so you can use it to do realtime updates
	 *
	 * @return void
	 */
	public function ajax() {
		echo json_encode($this->getTweets());
	}

	/* Getters/Setters
	 */

	/**
	 * Sets the Strategy used to load tweets
	 *
	 * @param $loader
	 *
	 * @return TwitterBasicLoader
	 */
	public function setLoader($loader) {
		$this->loader = $loader;
	}

	/**
	 * Gets the Strategy used to load tweets
	 *
	 * @return TwitterBasicLoader
	 */
	public function getLoader() {
		return $this->loader;
	}

	/**
	 * Set the max number of tweets to preprocess
	 *
	 * Chainable method
	 *
	 * @param  $maxTweets
	 * @return void
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
	public function getMaxTweets() {
		return $this->maxTweets;
	}

	/**
	 * Set filtration class for tweet content
	 *
	 * @param  $filter
	 * @return void
	 */
	public function setFilter($filter) {
		$this->filter = $filter;
	}

	/**
	 * Get filtration class for tweet content
	 *
	 * @return null|TwitterFilter
	 */
	public function getFilter() {
		return $this->filter;
	}

	/**
	 * Set preprocessed tweets
	 *
	 * @param  $tweets
	 * @return void
	 */
	public function setTweets($tweets) {
		$this->tweets = $tweets;
	}

	/**
	 * Get preprocessed tweets
	 *
	 * @return
	 */
	public function getTweets() {
		return $this->tweets;
	}
}

