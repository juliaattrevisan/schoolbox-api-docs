<?php
/**
 * Model for Schoolbox resources within the file system.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox;

use Alaress_Date_Format;
use Schoolbox\Resource;

/**
 * Class MyFile
 *
 * @package Schoolbox
 * @deprecated in favour of Resource\My\File\Model, this class retained for backward compatibility
 */
class MyFile extends Resource\My\File\Model implements FileInterface {

	/**
     * @deprecated
	 * @var IConfigLoader
	 */
	private $config_loader;

	/**
	 * Location of file prior to creation
     * @deprecated
	 * @var string
	 */
	private $tempFileLocation;

	/**
	 * Creates object and sets up sub-objects.
     * @deprecated
	 */
	public function __construct() {
		//
		// Don't really want to load a mapper here, but meh.
        // TODO make this go away
		$this->config_loader = new Config\Loader();
	}


	/**
     * @deprecated
	 * @param  int $filesize
	 * @return $this
	 */
	public function setFilesize($filesize) {
		return $this->setSize($filesize);
	}

	/**
     * @deprecated
	 * @return int
	 */
	public function getFilesize() {
		return $this->getSize();
	}


	/**
     * @deprecated
	 * @param  IConfigLoader $config_loader
	 * @return $this
	 */
	public function setConfigLoader(IConfigLoader $config_loader) {
		$this->config_loader = $config_loader;
		return $this;
	}

	/**
     * @deprecated
	 * @return IConfigLoader
	 */
	public function getConfigLoader() {
		return $this->config_loader;
	}


	/**
	 * Set deleted
	 *
     * @deprecated
	 * @param bool $deleted
	 */
	public function setDeleted($deleted) {
		$this->setDeletedAt($deleted ? Alaress_Date_Format::now() : null);
	}

    /**
     * Get deleted
     *
     * @deprecated
     * @return bool
     */
	public function getDeleted() {
		return $this->getDeletedAt() instanceof Alaress_Date_Format && !$this->getDeletedAt()->isNull();
	}


    /**
     * @deprecated
     * @return bool
     */
    public function isDeleted() {
        return $this->getDeleted();
    }

	/**
	 * Use this function to set the location of the file when creating a new file record
     * @deprecated
	 * @param string $tempFileLocation
	 */
	public function setTempFileLocation($tempFileLocation) {
		$this->tempFileLocation = $tempFileLocation;
	}

	/**
	 * This function only exists to move the uploaded file into the correct location on save
     * @deprecated
	 * @return string
	 */
	public function getTempFileLocation() {
		return $this->tempFileLocation;
	}

    /**
     * Return location of file in filesystem
     * @deprecated
     * @return string
     */
    public function getFileLocation()
    {
        return $this->config_loader->get('myfile_dir') . '/' . $this->getId() . '.dat';
    }

}
