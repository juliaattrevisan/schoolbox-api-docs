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

class File {
	/**
	 * @var int
	 */
	protected $id;

	/**
	 * @var ConfigLoader
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
	 * @param \Schoolbox\ConfigLoader $config_loader
	 */
	public function setConfigLoader($config_loader) {
		$this->config_loader = $config_loader;
	}

	/**
	 * @return \Schoolbox\ConfigLoader
	 */
	public function getConfigLoader() {
		return $this->config_loader;
	}
}