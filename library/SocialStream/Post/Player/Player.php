<?php
/**
 * SocialStream_Post_Player_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Player;


/**
 * Class Thumbnail
 * @package SocialStream\Post\Player
 */
abstract class Player {

	/**
	 * The url to fetch
	 *
	 * @var string
	 */
	protected $url = '';

	/**
	 * Next class to deal with the problem
	 *
	 * @var
	 */
	protected $next = false;

	/**
	 * Constructor
	 *
	 * @param $newPost
	 */
	public function __construct($newPost) {
		$this->setUrl($newPost->getUrl());
	}

	/**
	 * Subclasses define how to fetch thumbnails
	 *
	 * @return mixed
	 */
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
	 * Set url
	 *
	 * @param $url
	 */
	protected function setUrl($url) {
		$this->url = $url;
	}

	/**
	 * Get url
	 *
	 * @return string
	 */
	protected function getUrl() {
		return $this->url;
	}
}
