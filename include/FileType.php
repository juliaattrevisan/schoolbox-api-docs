<?php
/**
 * FileType
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class FileType extends DeprecatedModel {
	public $_table = 'allowedFileTypes';
	public $_index = 'fileTypeId';

	/**
	 * Constructor
	 *
	 * @param $path
	 * @param null $mime
	 */
	public function __construct($path, $mime = null) {
		$this->setPath($path);
		$this->setMime($mime);

		parent::__construct();
	}

	/**
	 * Determine the file extension for a given filename/path
	 * without dot
	 *
	 * @return string
	 */
	public function getExtension() {
		return substr(strrchr($this->getPath(), '.'), 1);
	}

	/**
	 * Determine if $path has an valid image extension
	 *
	 * @return bool
	 */
	public function isImageExtension() {
		if (in_array($this->getExtension(), array('jpg', 'jpeg', 'gif', 'png'))) {
			return true;
		}

		return false;
	}

	/**
	 * Determine if a file is audio
	 *
	 * @return bool
	 */
	public function isAudio() {
		if (strstr($this->getMime(), 'audio')) {
			return true;
		}

		return false;
	}

	/**
	 * Determine if a file is video
	 *
	 * @return bool
	 */
	public function isVideo() {
		if (strstr($this->getMime(), 'video')) {
			return true;
		}

		return false;
	}

	/**
	 * Determine if a file is an image
	 *
	 * @return bool
	 */
	public function isImage() {
		if (strstr($this->getMime(), 'image')) {
			return true;
		}

		return false;
	}

	/**
	 * Find out what the iconFile for a particular mime type is
	 *
	 * @param $mimeType
	 * @return mixed
	 */
	public function getIconFile($mimeType) {
		$query = "
			SELECT iconFile
			FROM {$this->_table}
			WHERE mime = '{$mimeType}'
			LIMIT 1
		";

		$mimeType = $this->db->getRow($query);

		return $mimeType['iconFile'];
	}

	/* Getters/Setters
	 */

	/**
	 * Set path
	 *
	 * @param  $val
	 * @return void
	 */
	public function setPath($val) {
		$this->path = $val;
	}

	/**
	 * Get path
	 *
	 * @return null
	 */
	private function getPath() {
		return $this->path;
	}

	/**
	 * Set mime. This is used to determine whether a file is audio or video
	 * based, and should be extended if necessary
	 *
	 * @param  $val
	 * @return void
	 */
	public function setMime($val) {
		$this->mime = $val;
	}

	/**
	 * Get mime
	 *
	 * @return null
	 */
	private function getMime() {
		return $this->mime;
	}
}
