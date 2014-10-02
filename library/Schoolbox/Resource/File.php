<?php
/**
 * Model for files in the resource browser.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Resource;
use Schoolbox\ConfigLoader;
use Schoolbox\FileInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\ISoftDeletable;
use Schoolbox\User;
use Schoolbox\File\Mimetype;

class File implements ISoftDeletable, FileInterface {
	/**
	 * @var int
	 */
	protected $id;

	/**
	 * @var string
	 */
	protected $filename;

    /**
     * Location of file prior to creation
     * @var string
     */
    protected $tempFileLocation;

    /**
	 * @var string
	 */
	protected $mimeType;

	/**
	 * @var User
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
	 * @var string
	 */
	protected $description;

	/**
	 * @var string
	 */
	protected $comment;

	/**
	 * @var bool
	 */
	protected $hidden;

	/**
	 * @var Category
	 */
	protected $folder;

	/**
	 * @var int
	 */
	protected $filesize;

	/**
	 * @var IConfigLoader
	 */
	protected $config_loader;

	/**
	 * @var bool
	 */
	protected $deleted;


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
	 * @param \Schoolbox\File\Mimetype $mimeType
	 */
	public function setMimeType($mimeType) {
		$this->mimeType = $mimeType;
	}

    /**
     * Return a mime type object, if none is found a blank object is returned
     * @return \Schoolbox\File\Mimetype
     */
    public function getMimetype() {
        if($this->mimeType instanceof Mimetype)
            return $this->mimeType;
        else
            return new Mimetype();
    }


    /**
     * @return string
     */
    public function getMime() {
        return $this->getMimeType()->getMime();
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
	 * @param User $owner
	 * @return $this
	 */
	public function setOwner($owner) {
		$this->owner = $owner;
		return $this;
	}

	/**
	 * @return User
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

	/**
	 * @param string $comment
	 */
	public function setComment($comment) {
		$this->comment = $comment;
	}

	/**
	 * @return string
	 */
	public function getComment() {
		return $this->comment;
	}

	/**
	 * @param string $description
	 */
	public function setDescription($description) {
		$this->description = $description;
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
	}


    /**
     * @param bool $hidden
     */
    public function setHidden($hidden) {
		$this->hidden = $hidden;
	}


    /**
     * @return bool
     */
    public function getHidden() {
		return $this->hidden;
	}


    /**
     * @param Category $folder
     */
    public function setFolder($folder) {
		$this->folder = $folder;
	}


    /**
     * @return Category
     */
    public function getFolder() {
		return $this->folder;
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
	 * @param bool $deleted
	 *
	 * @return $this
	 */
	public function setDeleted($deleted) {
		$this->deleted = $deleted;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function getDeleted() {
		return $this->deleted;
	}


    /**
     * @return bool
     */
    public function isDeleted() {
		return $this->getDeleted();
	}

    /**
     * Use this function to set the location of the file when creating a new file record
     * @param mixed $tempFileLocation
     */
    public function setTempFileLocation($tempFileLocation)
    {
        $this->tempFileLocation = $tempFileLocation;
    }

    /**
     * This function only exists to move the uploaded file into the correct location on save
     * @return string
     */
    public function getTempFileLocation()
    {
        return $this->tempFileLocation;
    }
}