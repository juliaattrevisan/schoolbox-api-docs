<?php
/**
 * SocialStream_Post_Metadata_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Metadata;


/**
 * Class Metadata
 * @package SocialStream\Post\Metadata
 */
abstract class Metadata {

	/**
	 * A curl handler
	 *
	 * @var string
	 */
	protected $curl = null;

	/**
	 * Url title
	 *
	 * @var string
	 */
	protected $title = '';

	/**
	 * Url description
	 *
	 * @var string
	 */
	protected $description = '';

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
	 * @param $curl
	 * @param $newPost
	 */
	public function __construct($curl, $newPost) {
		$this->setCurl($curl);
		$this->setUrl($newPost->getUrl());
	}

	/**
	 * Subclasses define how to fetch thumbnails
	 *
	 * @return mixed
	 */
	abstract public function fetchMetadata();

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
	protected function setCurl($curl) {
		$this->curl = $curl;
	}

	/**
	 * Get curl
	 *
	 * @return string
	 */
	protected function getCurl() {
		return $this->curl;
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

	/**
	 * Set description
	 *
	 * @param string $description
	 */
	protected function setDescription($description) {
		$this->description = $description;
	}

	/**
	 * Get description
	 *
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 * Set title
	 *
	 * @param string $title
	 */
	protected function setTitle($title) {
		$this->title = $title;
	}

	/**
	 * Get title
	 *
	 * @return string
	 */
	public function getTitle() {
		return $this->title;
	}
}
