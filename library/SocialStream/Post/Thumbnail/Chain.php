<?php
/**
 * Chain of Responsibility for obtaining urls to a thumbnail image
 * from any given url, whether that's a direct link to a file on the
 * internet, or just some website with an opengraph avatar etc.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Thumbnail;

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
		$curl = new \Alaress_Curl_Url($newPost->getUrl());
		$curl->exec();

		/* Set up chain of responsibility
		 */
		$direct = new Direct($curl, $newPost);
		$opengraph = new OpenGraph($curl, $newPost);
		$itempropImage = new ItempropImage($curl, $newPost);
		$itempropPhoto = new ItempropPhoto($curl, $newPost);

		/* Try directly, otherwise try the other meta tag variants in sequence
		 * until one of them matches
		 */
		$itempropImage->setNext($itempropPhoto);
		$opengraph->setNext($itempropImage);
		$direct->setNext($opengraph);

		$result = $direct->fetchThumbnail();

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