<?php
/**
 * Model for files in the resource browser.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Resource;
use Alaress_Date_Format;
use Schoolbox\ConfigLoader;
use Schoolbox\FileInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\File\Mimetype;

/**
 * Class File
 *
 * @deprecated in favour of Resource\File\Model, this class retained for backward compatibility
 * @package Schoolbox\Resource
 */
class File extends File\Model implements FileInterface {

    /**
     * Location of file prior to creation
     * @var string
     */
    private $tempFileLocation;

	/**
	 * @var IConfigLoader
	 */
    private $config_loader;


	/**
	 * Creates object and sets up sub-objects.
     * @deprecated
	 */
	public function __construct() {
		$this->config_loader = new ConfigLoader();
	}

	/**
	 * Gets the location at which this file would be stored.
	 *
     * @deprecated
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
     * @deprecated
	 * @param \Schoolbox\File\Mimetype $mimeType
     * @return self
	 */
	public function setMimeType($mimeType) {
		$this->getStorage()->setMimetype($mimeType);
        return $this;
	}

    /**
     * Return a mime type object, if none is found a blank object is returned
     *
     * @deprecated
     * @return \Schoolbox\File\Mimetype
     */
    public function getMimetype() {
        if($this->getStorage()->getMimetype() instanceof Mimetype)
            return $this->getStorage()->getMimetype();
        else
            return new Mimetype();
    }

	/**
     * @deprecated
	 * @param  Alaress_Date_Format $modified
	 * @return $this
	 */
	public function setModified($modified) {
        return $this->setModifiedAt($modified);
	}

	/**
     * @deprecated
	 * @return Alaress_Date_Format
	 */
	public function getModified() {
		return $this->getModifiedAt();
	}

	/**
     * @deprecated
	 * @param  Alaress_Date_Format $submitted
	 * @return $this
	 */
	public function setSubmitted($submitted) {
		return $this->setCreatedAt($submitted);
	}

	/**
     * @deprecated
	 * @return Alaress_Date_Format
	 */
	public function getSubmitted() {
		return $this->getCreatedAt();
	}

	/**
     * @deprecated
	 * @param  \Schoolbox\IConfigLoader $config_loader
	 * @return $this
	 */
	public function setConfigLoader(IConfigLoader $config_loader) {
		$this->config_loader = $config_loader;
		return $this;
	}

	/**
     * @deprecated
	 * @return \Schoolbox\IConfigLoader
	 */
	public function getConfigLoader() {
		return $this->config_loader;
	}

	/**
     * @deprecated
	 * @param string $comment
     * @return self
	 */
	public function setComment($comment) {
		$this->description = $comment;
	}

	/**
     * @deprecated
	 * @return string
	 */
	public function getComment() {
		return $this->description;
	}

    /**
     * @deprecated
     * @param string $description
     * @return self
     */
    public function setDescription($description) {
        return $this->setTitle($description);
    }

    /**
     * @deprecated
     * @return string
     */
    public function getDescription()
    {
        return $this->getTitle();
    }

    /**
     * @deprecated
     * @param int $filesize
     * @return self
     */
    public function setFilesize($filesize) {
        $this->getStorage()->setSize($filesize);
        return $this;
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
	 * @param bool $deleted
	 * @return $this
	 */
	public function setDeleted($deleted) {
		return $this->setDeletedAt($deleted ? Alaress_Date_Format::now() : null);
	}

	/**
     * @deprecated
	 * @return bool
	 */
	public function getDeleted() {
		return $this->getDeletedAt() instanceof Alaress_Date_Format && !$this->getDeletedAt()->isNull();
	}

    /**
     * Use this function to set the location of the file when creating a new file record
     *
     * @deprecated
     * @param mixed $tempFileLocation
     */
    public function setTempFileLocation($tempFileLocation)
    {
        $this->tempFileLocation = $tempFileLocation;
    }

    /**
     * This function only exists to move the uploaded file into the correct location on save
     *
     * @deprecated
     * @return string
     */
    public function getTempFileLocation()
    {
        return $this->tempFileLocation;
    }
}