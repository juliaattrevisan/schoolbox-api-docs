<?php
/**
 * Chain
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Video;


class Chain {

	/**
	 * Resultant thumbnail poster and source
	 *
	 * @var array
	 */
	private $result = array();

	/**
	 * Constructor
	 *
	 * @param $url
	 */
	public function __construct($url) {

		/* Set up chain of responsibility
		 */
		$storage = new Storage($url);
		$storage->setNext(new Resource($url));
		$result = $storage->fetchThumbnail();
		$this->setResult($result);
	}

	/* Getters/Setters
 	 */

	/**
	 * Set result
	 *
	 * @param array $result
	 */
	private function setResult($result) {
		$this->result = $result;
	}

	/**
	 * @return array
	 */
	public function getResult() {
		return $this->result;
	}
}