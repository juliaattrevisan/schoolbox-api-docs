<?php
/**
 * SocialStream_File
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream;

class File {

	/**
	 * The url to curl, to obtain an file
	 */
	private $url = '';

	/**
	 * The owner of the file
	 */
	private $ownerId = -1;

	/**
	 * An instance of Schoolbox\File
	 *
	 * @var null
	 */
	private $savedFile = null;

	/**
	 * Constructor
	 *
	 * Finds the file at url $url
	 * @param $url
	 * @param $ownerId
	 */
	public function __construct($url, $ownerId) {
		$this->setUrl($url);
		$this->setOwnerId($ownerId);
	}

	/**
	 * Save image
	 */
	public function saveImage() {
		$brownhole = tmpfile();

		$curl = new \Alaress_Curl_Url($this->getUrl());

		$curl->addOptions(array(
			CURLOPT_FILE => $brownhole,
		));

		$curl->exec();

		$metadata = stream_get_meta_data($brownhole);

		if (file_exists($metadata['uri'])) {
			$savedFile = \Schoolbox\FileMapper::getInstance()->upload(basename($this->getUrl()), $metadata['uri'], $this->getOwnerId());

			$this->setSavedFile($savedFile);
		}

		fclose($brownhole);
	}

	/* Getters/Setters
	 */

	/**
	 * Set owner id
	 *
	 * @param $ownerId
	 */
	public function setOwnerId($ownerId) {
		$this->ownerId = $ownerId;
	}

	/**
	 * Get owner id
	 *
	 * @return int
	 */
	public function getOwnerId() {
		return $this->ownerId;
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

	/**
	 * Set saved file
	 *
	 * @param null $savedFile
	 */
	public function setSavedFile($savedFile) {
		$this->savedFile = $savedFile;
	}

	/**
	 * Get saved file
	 *
	 * @return null
	 */
	public function getSavedFile() {
		return $this->savedFile;
	}
}
