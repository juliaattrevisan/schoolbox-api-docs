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
	 * Next class to deal with the problem
	 *
	 * @var
	 */
	protected $next = false;

	/**
	 * An instance of FileType
	 *
	 * This checks whether a url contains a valid image or not
	 * by checking file extension
	 *
	 * @var null
	 */
	protected $fileType = null;

	/**
	 * Constructor
	 *
	 * @param $curl
	 * @param $newPost
	 */
	public function __construct($curl, $newPost) {
		$this->setCurl($curl);
		$this->setUrl($newPost->getUrl());
		$this->setFileType(new \FileType($newPost->getUrl()));
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
	 * Set file type
	 *
	 * @param null $fileType
	 */
	protected function setFileType($fileType) {
		$this->fileType = $fileType;
	}

	/**
	 * Get file type
	 *
	 * @return null
	 */
	protected function getFileType() {
		return $this->fileType;
	}
}
