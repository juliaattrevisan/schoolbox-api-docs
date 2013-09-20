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
	 * The xpath query
	 *
	 * @var string
	 */
	protected $query = '';

	/**
	 * These are the attributes that might contain a link
	 * to an image you want to retrieve
	 *
	 * @var array
	 */
	protected $contentAttributes = array();

	/**
	 * Constructor
	 *
	 * @param $xml
	 * @param $url
	 */
	public function __construct($xml, $url) {
		$this->setXml($xml);
		$this->setUrl($url);
		$this->setFileType(new \FileType($url));
	}

	/**
	 * Subclasses define how to fetch thumbnails
	 *
	 * @return mixed
	 */
	public function fetchThumbnail() {
		$allElements = $this->getXml()->query($this->getQuery());

		foreach($allElements as $currentElement) {
			foreach($currentElement->attributes as $currentAttribute) {
				if (in_array($currentAttribute->nodeName, $this->getContentAttributes())) {
					$match = trim($currentAttribute->nodeValue);

					/* Check this path for extension and if it is an image extension,
					 * presume something useful was found
					 */
					$this->getFileType()->setPath($match);

					if ($this->getFileType()->isImageExtension()) {

						/* Derive absolute url if the image url is actually
						 * specified relatively (www.google.com)
						 */
						if ($match[0] == '/') {
							$bits = parse_url($this->getUrl());
							$url = 'http://' . $bits['host'] . $match;

							return $url;
						}
						else {
							return $match;
						}
					}
				}
			}
		}

		/* Otherwise get the next class to deal with it
		 */
		if ($this->getNext()) {
			return $this->getNext()->fetchThumbnail();
		}
	}

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

	/**
	 * Set content attributes
	 *
	 * @param array $contentAttributes
	 */
	protected function setContentAttributes($contentAttributes) {
		$this->contentAttributes = $contentAttributes;
	}

	/**
	 * Get content attributes
	 *
	 * @return array
	 */
	protected function getContentAttributes() {
		return $this->contentAttributes;
	}

	/**
	 * Set query
	 *
	 * @param string $query
	 */
	protected function setQuery($query) {
		$this->query = $query;
	}

	/**
	 * Get query
	 *
	 * @return string
	 */
	protected function getQuery() {
		return $this->query;
	}
}
