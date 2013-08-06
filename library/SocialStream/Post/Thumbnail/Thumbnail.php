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
	 * XML output
	 *
	 * @var null
	 */
	protected $xml = null;

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
	 * @param $xml
	 * @param $newPost
	 */
	public function __construct($xml, $newPost) {
		$this->setXml($xml);
		$this->setUrl($newPost->getUrl());
		$this->setFileType(new \FileType($newPost->getUrl()));
	}

	/**
	 * Subclasses define how to fetch thumbnails
	 *
	 * @return mixed
	 */
	abstract public function fetchThumbnail();
//
//	/**
//	 * Try to find one of the patterns in $allPatterns
//	 *
//	 * @param $allPatterns
//	 * @return mixed
//	 */
//	protected function analyse($allPatterns) {
//		$matches = array();
//
//		for ($i=0, $found=false; ($i < count($allPatterns)) && !$found; $i++) {
//			if (preg_match($allPatterns[$i], $this->getCurl()->getResult(), $matches)) {
//
//				/* Check this path for extension and if it is an image extension,
//				 * presume something useful was found
//				 */
//				$this->getFileType()->setPath($matches[1]);
//
//				if ($this->getFileType()->isImageExtension()) {
//					$found = true;
//				}
//			}
//		}
//
//		/* If an opengraph image was found, curl that too
//		 */
//		if ($found) {
//			return $matches[1];
//		}
//
//		/* Otherwise get the next class to deal with it
//		 */
//		if ($this->getNext()) {
//			return $this->getNext()->fetchThumbnail();
//		}
//	}

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

	/**
	 * Set xml
	 *
	 * @param null $xml
	 */
	protected function setXml($xml) {
		$this->xml = $xml;
	}

	/**
	 * Get xml
	 *
	 * @return null
	 */
	protected function getXml() {
		return $this->xml;
	}
}
