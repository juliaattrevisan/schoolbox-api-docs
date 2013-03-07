<?php
/**
 * Model for Schoolbox resources within the file system.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Resource;
use \Cfg;

class File {

	/**
	 * @var int
	 */
	protected $id;

	/**
	 * @var string
	 */
	protected $filename;

	/**
	 * @var string
	 */
	protected $hash;

	/**
	 * @var int
	 */
	protected $filesize;

	/**
	 * @var string
	 */
	protected $mime;

	/**
	 * @var int
	 */
	protected $uploaded_by;

	/**
	 * @var string
	 */
	protected $uploaded_on;

	/**
	 * @var string
	 */
	protected $last_accessed;

	/**
	 * Temporary location for the file. Never persisted
	 *
	 * @var string
	 */
	protected $temp_location;


	/**
	 * @param string $filename
	 */
	public function setFilename($filename) {
		$this->filename = $filename;
	}

	/**
	 * @return string
	 */
	public function getFilename() {
		return $this->filename;
	}

	/**
	 * @param string $hash
	 */
	public function setHash($hash) {
		$this->hash = $hash;
	}

	/**
	 * @return string
	 */
	public function getHash() {
		return $this->hash;
	}

	/**
	 * @param int $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @param int $filesize
	 */
	public function setFilesize($filesize) {
		$this->filesize = $filesize;
	}

	/**
	 * @return int
	 */
	public function getFilesize() {
		return $this->filesize;
	}

	/**
	 * @param string $last_accessed
	 */
	public function setLastAccessed($last_accessed) {
		$this->last_accessed = $last_accessed;
	}

	/**
	 * @return string
	 */
	public function getLastAccessed() {
		return $this->last_accessed;
	}

	/**
	 * @param string $mime
	 */
	public function setMime($mime) {
		$this->mime = $mime;
	}

	/**
	 * @return string
	 */
	public function getMime() {
		return $this->mime;
	}

	/**
	 * @param string $temp_location
	 */
	public function setTempLocation($temp_location) {
		$this->temp_location = $temp_location;
	}

	/**
	 * @return string
	 */
	public function getTempLocation() {
		return $this->temp_location;
	}

	/**
	 * @param int $uploaded_by
	 */
	public function setUploadedBy($uploaded_by) {
		$this->uploaded_by = $uploaded_by;
	}

	/**
	 * @return int
	 */
	public function getUploadedBy() {
		return $this->uploaded_by;
	}

	/**
	 * @param string $uploaded_on
	 */
	public function setUploadedOn($uploaded_on) {
		$this->uploaded_on = $uploaded_on;
	}

	/**
	 * @return string
	 */
	public function getUploadedOn() {
		return $this->uploaded_on;
	}

	/**
	 * Gets the location at which this file would be stored.
	 *
	 * @return string
	 */
	public function getFileLocation() {
		$id = $this->getId();
		if (!isset($id)) {
			return false;
		}
		return Cfg::get('storage_dir') . '/' . $id . '.dat';
	}

	/**
	 * Loads the hash for this file, based on the file hash.
	 *
	 * @return bool
	 */
	public function loadHashFromTempLocation() {
		if (file_exists($temp_location = $this->getTempLocation())) {
			$this->setHash(sha1_file($temp_location));
			return true;
		}
		return false;
	}

	/**
	 * Loads the mime type for this file, based on the filename.
	 *
	 * @return bool
	 */
	public function loadMimetypeFromFilename() {
		$filename = $this->getFilename();
		if (!isset($filename)) {
			return false;
		}
		//
		// Don't really want to load a mapper here, but meh.
		$mapper = new MimetypeMapper();
		if ($mimetype = $mapper->getMimetypeForFilename($filename)) {
			$this->setMime($mimetype);
			return true;
		}
		return false;
	}

	/**
	 * Loads the file size for this file, based on the temp location.
	 *
	 * @return bool
	 */
	public function loadFilesizeFromTempLocation() {
		if (file_exists($temp_location = $this->getTempLocation())) {
			$this->setFilesize(filesize($temp_location));
			return true;
		}
		return false;
	}

	/**
	 * Copies file from its temp location to permanent upload location.
	 *
	 * @return bool
	 */
	public function copyFromTempLocation() {
		$temp_location = $this->getTempLocation();
		$file_location = $this->getFileLocation();
		if (file_exists($temp_location) && $file_location) {
			return copy($temp_location, $file_location);
		}
		return false;
	}

	/**
	 * Gets the icon for this file from its filename.
	 *
	 * @return string
	 */
	public function getIconFromFilename() {
		$mapper = new MimetypeMapper();
		return $mapper->getIconForFilename($this->getFilename());
	}

	/**
	 * Deletes file from permanent upload location.
	 *
	 * @return bool
	 */
	public function deleteFile() {
		if (file_exists($file_location = $this->getFileLocation())) {
			return unlink($file_location);
		}
	}
}
