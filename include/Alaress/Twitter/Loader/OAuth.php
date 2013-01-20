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
 *
 * To change the API resource, use $twitter->getLoader()->setResource('statuses/mentions');
 */
class Alaress_Twitter_Loader_OAuth implements Alaress_Twitter_Loader_Interface {

	/* REST API method. Defaults to statuses/user_timeline
	 * See https://dev.twitter.com/docs/api
	 */
	private $resource = 'statuses/user_timeline';

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
	public function load($resource = null, $params = null) {
		if (!isset($resource)) {
			$resource = $this->getResource();
		}
		if (!isset($params)) {
			$params = $this->getParams();
		}
		$result = $this->getConnection()->get($resource, $params);
		$code   = intval($this->getConnection()->http_code);
		if ($code >= 400) {
			//
			// Log errors somewhere, and return an empty array?
//			var_dump($result->errors);
//			throw new Alaress_Twitter_Loader_Exception($result->errors);
			return array();
		}
		return $result->statuses;
	}

	/* Getters/Setters
	 */

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

	/**
	 * Set resource
	 *
	 * Support chained methods
	 *
	 * @param $resource
	 * @return \Alaress_Twitter_Loader_OAuth
	 */
	public function setResource($resource) {
		$this->resource = $resource;

		return $this;
	}

	/**
	 * Get resource
	 *
	 * @return string
	 */
	private function getResource() {
		return $this->resource;
	}

	/**
	 * Gets URL parameters that will be sent to Twitter interface.
	 *
	 * @return mixed
	 */
	public function getParams() {
		return $this->params;
	}

	/**
	 * Sets URL parameters that are sent to Twitter interface.
	 *
	 * @return $this
	 */
	public function setParams($params) {
		$this->params = $params;
		return $this;
	}
}
