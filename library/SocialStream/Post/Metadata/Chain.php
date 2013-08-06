<?php
/**
 * Chain
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Metadata;


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
	 * @param $curl
	 * @param $newPost
	 */
	public function __construct($curl, $newPost) {

		/* Set up chain of responsibility
		 */
		$opengraph = new OpenGraph($curl, $newPost);
		$metatag = new MetaTag($curl, $newPost);

		/* Try until one of them matches
		 */
		$opengraph->setNext($metatag);

		$result = $opengraph->fetchMetadata();

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