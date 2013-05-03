<?php
/**
 * Model for Schoolbox resources within the file system.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox;
use Schoolbox\ConfigLoader;
use Schoolbox\File\MimetypeMapper;

class MyFile {

	/**
	 * @var int
	 */
	protected $id;

	/**
	 * Parent?
	 *
	 * @var
	 */
	protected $parent;

	/**
	 * User id of the file owner
	 *
	 * @var
	 */
	protected $owner;

	/**
	 * The actual filename
	 *
	 * @var
	 */
	protected $realname;

	/**
	 * The datetime the file was added
	 *
	 * @var
	 */
	protected $created_at;

	/**
	 * A file description
	 *
	 * @var
	 */
	protected $description;

	/**
	 * The size of the file
	 *
	 * @var
	 */
	protected $filesize;

	/**
	 * The mime type
	 *
	 * @var string
	 */
	protected $mime;

	/**
	 * Whether this is softdeleted or not
	 *
	 * @var
	 */
	protected $deleted_at;

	/**
	 * @var IConfigLoader
	 */
	protected $config_loader;

	/**
	 * Creates object and sets up sub-objects.
	 */
	public function __construct() {
		//
		// Don't really want to load a mapper here, but meh.
		$this->config_loader   = new ConfigLoader();
	}

	/**
	 * @param  string $filename
	 * @return $this
	 */
	public function setFilename($filename) {
		$this->filename = $filename;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getFilename() {
		return $this->filename;
	}

	/**
	 * @param  int $id
	 * @return $this
	 */
	public function setId($id) {
		$this->id = $id;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @param  int $filesize
	 * @return $this
	 */
	public function setFilesize($filesize) {
		$this->filesize = $filesize;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getFilesize() {
		return $this->filesize;
	}

	/**
	 * Set mime object
	 *
	 * @param  string $mime
	 * @return $this
	 */
	public function setMime($mime) {
		$this->mime = $mime;

		return $this;
	}

	/**
	 * Get mime object
	 *
	 * @return string
	 */
	public function getMime() {
		return $this->mime;
	}

	/**
	 * @param  \Schoolbox\IConfigLoader $config_loader
	 * @return $this
	 */
	public function setConfigLoader(IConfigLoader $config_loader) {
		$this->config_loader = $config_loader;
		return $this;
	}

	/**
	 * @return \Schoolbox\IConfigLoader
	 */
	public function getConfigLoader() {
		return $this->config_loader;
	}

	/**
	 * Set parent id
	 *
	 * @param  $parent
	 */
	public function setParent($parent) {
		$this->parent = $parent;
	}

	/**
	 * Get parent id
	 *
	 * @return
	 */
	public function getParent() {
		return $this->parent;
	}

	/**
	 * Set owner id
	 *
	 * @param  $owner
	 */
	public function setOwner($owner) {
		$this->owner = $owner;
	}

	/**
	 * Get owner id
	 *
	 * @return
	 */
	public function getOwner() {
		return $this->owner;
	}

	/**
	 * Set created at
	 *
	 * @param  $created_at
	 */
	public function setCreatedAt($created_at) {
		$this->created_at = $created_at;
	}

	/**
	 * Get created at
	 *
	 * @return
	 */
	public function getCreatedAt() {
		return $this->created_at;
	}

	/**
	 * Set description
	 *
	 * @param  $description
	 */
	public function setDescription($description) {
		$this->description = $description;
	}

	/**
	 * Get description
	 *
	 * @return
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 * Set deleted at
	 *
	 * @param  $deleted_at
	 */
	public function setDeletedAt($deleted_at) {
		$this->deleted_at = $deleted_at;
	}

	/**
	 * Get deleted at
	 *
	 * @return
	 */
	public function getDeletedAt() {
		return $this->deleted_at;
	}
}
