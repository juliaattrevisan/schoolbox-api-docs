<?php
/**
 * Model for Schoolbox resources within the file system.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox;
use Schoolbox\File\Mimetype;
use Schoolbox\Resource\Storage\IStorage;

/**
 * Class Storage
 *
 * @deprecated use Schoolbox\Resource\Storage\Model
 * @package Schoolbox
 */
class Storage implements FileInterface, IStorage {

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
	 * @var User
	 */
	protected $uploaded_by;

	/**
	 * @var \Alaress_Date_Format
	 */
	protected $uploaded_on;

	/**
	 * @var \Alaress_Date_Format
	 */
	protected $last_accessed;

    /** @var  \Alaress_Date_Format */
    protected $expired_at;

	/**
	 * Temporary location for the file. Never persisted
	 *
	 * @var string
	 */
	protected $temp_location;

	/**
	 * @var Mimetype
	 */
	protected $mimetype;

	/**
	 * @var IConfigLoader
	 */
	protected $config_loader;

	/**
	 * Creates object and sets up sub-objects.
	 */
	public function __construct() {
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
	 * @param  string $hash
	 * @return $this
	 */
	public function setHash($hash) {
		$this->hash = $hash;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getHash() {
		return $this->hash;
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
	 * @param  string $last_accessed
	 * @return $this
	 */
	public function setLastAccessed($last_accessed) {
		$this->last_accessed = $last_accessed;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getLastAccessed() {
		return $this->last_accessed;
	}

	/**
	 * @param  string $temp_location
	 * @return $this
	 */
	public function setTempLocation($temp_location) {
		$this->temp_location = $temp_location;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getTempLocation() {
		return $this->temp_location;
	}

	/**
	 * @param User $uploaded_by
	 * @return $this
	 */
	public function setUploadedBy($uploaded_by) {
		$this->uploaded_by = $uploaded_by;
		return $this;
	}

	/**
	 * @return User
	 */
	public function getUploadedBy() {
		return $this->uploaded_by;
	}

	/**
	 * @param  \Alaress_Date_Format $uploaded_on
	 * @return $this
	 */
	public function setUploadedOn($uploaded_on) {
		$this->uploaded_on = $uploaded_on;
		return $this;
	}

	/**
	 * @return \Alaress_Date_Format
	 */
	public function getUploadedOn() {
		return $this->uploaded_on;
	}

    /**
     * @return \Alaress_Date_Format
     */
    public function getCreatedAt()
    {
        return $this->getUploadedOn();
    }

    /**
     * @param \Alaress_Date_Format $expired_at
     * @return self
     */
    public function setExpiredAt($expired_at)
    {
        $this->expired_at = $expired_at;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format
     */
    public function getExpiredAt()
    {
        return $this->expired_at;
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
		return $this->config_loader->get('storage_dir') . '/' . $id . '.dat';
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
	 * Deletes file from permanent upload location.
	 *
	 * @return bool
	 */
	public function deleteFile() {
		if (file_exists($file_location = $this->getFileLocation())) {
			return unlink($file_location);
		}
		return false;
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
     * @param \Schoolbox\File\Mimetype $mimetype
     * @return $this;
     */
    public function setMimetype(Mimetype $mimetype) {
        $this->mimetype = $mimetype;
        return $this;
    }

	/**
     * Return a mime type object, if none is found a blank object is returned
	 * @return \Schoolbox\File\Mimetype
	 */
	public function getMimetype() {
        if($this->mimetype instanceof Mimetype)
		    return $this->mimetype;
        else
            return new Mimetype();
    }


    /**
     * Returns the full mime string for the file
     *
     * @return string
     */
    public function getMime()
    {
        return $this->getMimetype()->getMime();
    }


    /**
	 * Is this file's name an allowed file name?
	 *
	 * @return bool
	 */
	public function isUploadAllowed() {
        return $this->getMimetype()->isEnabled();
	}


    /**
     * Returns the owner/uploader of the file
     *
     * @alias getUploadedBy
     * @return User
     */
    public function getOwner()
    {
        return $this->getUploadedBy();
    }


    /**
     * Returns the size on disk of the file
     *
     * @return int
     */
    public function getSize()
    {
        return $this->getFilesize();
    }

}
