<?php
/**
 * Model for Schoolbox resources within the file system.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox;

use Alaress_Date_Format;
use Schoolbox\File\Mimetype;

class MyFile implements FileInterface {

	/**
	 * @var int
	 */
	protected $id;

	/**
	 * Parent folder
	 *
	 * @var MyFolder
	 */
	protected $parent;

	/**
	 * User id of the file owner
	 *
	 * @var User
	 */
	protected $owner;

	/**
	 * The actual filename
	 *
	 * @var string
	 */
	protected $filename;

	/**
	 * The datetime the file was added
	 *
	 * @var Alaress_Date_Format
	 */
	protected $created_at;

	/**
	 * A file description
	 *
	 * @var string
	 */
	protected $description;

	/**
	 * The size of the file
	 *
	 * @var int
	 */
	protected $filesize;

	/**
	 * The mime type
	 *
	 * @var Mimetype
	 */
	protected $mime;

	/**
	 * Whether this is softdeleted or not
	 *
	 * @var bool
	 */
	protected $deleted;

	/**
	 * @var IConfigLoader
	 */
	protected $config_loader;

	/**
	 * Location of file prior to creation
	 * @var string
	 */
	protected $tempFileLocation;

	/**
	 * Creates object and sets up sub-objects.
	 */
	public function __construct() {
		//
		// Don't really want to load a mapper here, but meh.
		$this->config_loader = new Config\Loader();
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
	 * @param  MimeType $mime
	 * @return $this
	 */
	public function setMimetype($mime) {
		$this->mime = $mime;

		return $this;
	}

    /**
     * Get mime object
     * @return Mimetype
     */
    public function getMimetype()
    {
        return $this->mime;
    }

	/**
	 * Get mime string
	 *
	 * @return string
	 */
	public function getMime() {
		return $this->mime->getMime();
	}

	/**
	 * @param  IConfigLoader $config_loader
	 * @return $this
	 */
	public function setConfigLoader(IConfigLoader $config_loader) {
		$this->config_loader = $config_loader;
		return $this;
	}

	/**
	 * @return IConfigLoader
	 */
	public function getConfigLoader() {
		return $this->config_loader;
	}

	/**
	 * Set parent id
	 *
	 * @param MyFolder $parent
	 */
	public function setParent($parent) {
		$this->parent = $parent;
	}


    /**
     * Get parent id
     *
     * @return MyFolder
     */
	public function getParent() {
		return $this->parent;
	}

	/**
	 * Set owner id
	 *
	 * @param User $owner
	 */
	public function setOwner($owner) {
		$this->owner = $owner;
	}


    /**
     * Get owner id
     *
     * @return User
     */
	public function getOwner() {
		return $this->owner;
	}

	/**
	 * Set created at
	 *
	 * @param Alaress_Date_Format $created_at
	 */
	public function setCreatedAt($created_at) {
		$this->created_at = $created_at;
	}


    /**
     * Get created at
     *
     * @return Alaress_Date_Format
     */
	public function getCreatedAt() {
		return $this->created_at;
	}

	/**
	 * Set description
	 *
	 * @param string $description
	 */
	public function setDescription($description) {
		$this->description = $description;
	}


    /**
     * Get description
     *
     * @return string
     */
	public function getDescription() {
		return $this->description;
	}

	/**
	 * Set deleted
	 *
	 * @param bool $deleted
	 */
	public function setDeleted($deleted) {
		$this->deleted = $deleted;
	}

    /**
     * Get deleted
     *
     * @return bool
     */
	public function getDeleted() {
		return $this->deleted;
	}

	/**
	 * Use this function to set the location of the file when creating a new file record
	 * @param string $tempFileLocation
	 */
	public function setTempFileLocation($tempFileLocation) {
		$this->tempFileLocation = $tempFileLocation;
	}

	/**
	 * This function only exists to move the uploaded file into the correct location on save
	 * @return string
	 */
	public function getTempFileLocation() {
		return $this->tempFileLocation;
	}

    /**
     * Return location of file in filesystem
     * @return string
     */
    public function getFileLocation()
    {
        return $this->config_loader->get('myfile_dir') . '/' . $this->getId() . '.dat';
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
