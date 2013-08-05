<?php
/**
 * Chain
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Player;


class Chain {

	/**
	 * Resultant thumbnail url
	 *
	 * @var string
	 */
	private $result = '';

	/**
	 * Constructor
	 *
	 * @param $newPost
	 */
	public function __construct($newPost) {

		/* Set up chain of responsibility
		 */
		$youtube = new Youtube($newPost);
		$vimeo = new Vimeo($newPost);

		/* Try until one of them matches
		 */
		$youtube->setNext($vimeo);

		$result = $youtube->fetchThumbnail();

		$this->setResult($result);
	}

	/* Getters/Setters
 	 */

	/**
	 * Set result
	 *
	 * @param string $result
	 */
	private function setResult($result) {
		$this->result = $result;
	}

	/**
	 * @return string
	 */
	public function getResult() {
		return $this->result;
	}
}