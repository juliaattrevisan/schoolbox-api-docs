<?php
/**
 * FileType
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class FileType {
	private $path = null;
	private $mime = null;

	public function __construct($path, $mime = null) {
		$this->setPath($path);
		$this->setMime($mime);
	}

	/**
	 * Determine the file extension for a given filename/path
	 * without dot
	 *
	 * @param  $filename
	 * @return string
	 */
	public function getExtension() {
		return substr(strrchr($this->getPath(), '.'), 1);
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
	 * Set path
	 *
	 * @param  $val
	 * @return void
	 */
	private function setPath($val) {
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
	private function setMime($val) {
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
