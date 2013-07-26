<?php
/**
 * SocialStream_Post_Thumbnail_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Thumbnail;


/**
 * Class Thumbnail
 * @package SocialStream\Post\Thumbnail
 */
abstract class Thumbnail {

	/**
	 * A curl handler
	 *
	 * @var string
	 */
	protected $curl = null;

	/**
	 * The url to fetch
	 *
	 * @var string
	 */
	protected $url = '';

	/**
	 * Constructor
	 *
	 * @param $curl
	 * @param $newPost
	 */
	public function __construct($curl, $newPost) {
		$this->setCurl($curl);
		$this->setUrl($newPost->getUrl());
	}

	/**
	 * Next class to deal with the problem
	 *
	 * @var
	 */
	protected $next = false;

	abstract public function fetchThumbnail();

	/* Getters/Setters
	 */

	/**
	 * Set next
	 *
	 * @param  $next
	 */
	public function setNext($next) {
		$this->next = $next;
	}

	/**
	 * Get next
	 *
	 * @return bool
	 */
	public function getNext() {
		return $this->next;
	}

	/**
	 * Set curl
	 *
	 * @param $curl
	 */
	public function setCurl($curl) {
		$this->curl = $curl;
	}

	/**
	 * Get curl
	 *
	 * @return string
	 */
	public function getCurl() {
		return $this->curl;
	}

	/**
	 * Set url
	 *
	 * @param $url
	 */
	public function setUrl($url) {
		$this->url = $url;
	}

	/**
	 * Get url
	 *
	 * @return string
	 */
	public function getUrl() {
		return $this->url;
	}
}
