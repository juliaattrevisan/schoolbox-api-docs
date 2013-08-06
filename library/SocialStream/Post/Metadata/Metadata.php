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
	 * XML output from curl
	 *
	 * @var string
	 */
	protected $xml = null;

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
	 * @param $xml
	 * @param $newPost
	 */
	public function __construct($xml, $newPost) {
		$this->setXml($xml);
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

	/**
	 * Set xml
	 *
	 * @param string $xml
	 */
	protected function setXml($xml) {
		$this->xml = $xml;
	}

	/**
	 * Get xml
	 *
	 * @return string
	 */
	protected function getXml() {
		return $this->xml;
	}
}
