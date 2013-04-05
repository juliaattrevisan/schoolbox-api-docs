<?php
/**
 * Model for files in the resource browser.
 *
 * @todo Expand this and mapper for usages beyond old submission box files.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Resource;
use Schoolbox\ConfigLoader;
use Schoolbox\IConfigLoader;

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
	 * @var int
	 */
	protected $owner;

	/**
	 * @var \Alaress_Date_Format
	 */
	protected $submitted;

	/**
	 * @var \Alaress_Date_Format
	 */
	protected $modified;

	/**
	 * @var IConfigLoader
	 */
	protected $config_loader;

	/**
	 * Creates object and sets up sub-objects.
	 */
	public function __construct() {
		$this->config_loader = new ConfigLoader();
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
		return $this->config_loader->get('file_dir') . '/' . $id . '.dat';
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
	 * @param  \Alaress_Date_Format $modified
	 * @return $this
	 */
	public function setModified($modified) {
		$this->modified = $modified;
		return $this;
	}

	/**
	 * @return \Alaress_Date_Format
	 */
	public function getModified() {
		return $this->modified;
	}

	/**
	 * @param  int $owner
	 * @return $this
	 */
	public function setOwner($owner) {
		$this->owner = $owner;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getOwner() {
		return $this->owner;
	}

	/**
	 * @param  \Alaress_Date_Format $submitted
	 * @return $this
	 */
	public function setSubmitted($submitted) {
		$this->submitted = $submitted;
		return $this;
	}

	/**
	 * @return \Alaress_Date_Format
	 */
	public function getSubmitted() {
		return $this->submitted;
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
}