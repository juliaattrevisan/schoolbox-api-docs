<?php
/**
 * Mimetype
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\File;
use Config;

/**
 * Class Mimetype
 * @package Schoolbox\File
 */
class Mimetype {

	/**
	 * Allowed File id
	 *
	 * @var
	 */
	private $fileTypeID;

	/**
	 * Description for this mime type
	 *
	 * @var
	 */
	private $description;

	/**
	 * Mime type
	 *
	 * @var
	 */
	private $mime;

	/**
	 * Filename representing this mime type
	 *
	 * @var
	 */
	private $iconFile;

	/**
	 * Inline
	 *
	 * @var
	 */
	private $inline;

	/**
	 * Enable
	 *
	 * @var
	 */
	private $enable;

    /**
     * Max file size allowed for mime
     *
     * @var int
     */
    private $maxFileSize;

    /**
	 * Get icon class by deriving it from the iconFile
	 *
	 * This previously abused the \Url class, but now it's all self-contained here and independent.
	 *
	 * @return mixed
	 */
	public function getClass() {
		return preg_replace('/[^a-zA-Z]/', '', substr(strtolower($this->getIconFile()), 0, strrpos($this->getIconFile(), ".")));
	}

	/* Getters/Setters
	 */

	/**
	 * Set description
	 *
	 * @param $description
	 */
	public function setDescription($description) {
		$this->description = $description;
	}

	/**
	 * Get description
	 *
	 * @return mixed
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 * Set enable
	 *
	 * @param $enable
	 * @return void
	 */
	public function setEnable($enable) {
		$this->enable = $enable;
	}

	/**
	 * Get enable
	 *
	 * @return mixed
	 */
	public function getEnable() {
		return $this->enable;
	}

	/**
	 * Set file type id
	 *
	 * @param $fileTypeID
	 * @return void
	 */
	public function setFileTypeID($fileTypeID) {
		$this->fileTypeID = $fileTypeID;
	}

	/**
	 * Get file type id
	 *
	 * @return mixed
	 */
	public function getFileTypeID() {
		return $this->fileTypeID;
	}

	/**
	 * Alias method
	 *
	 * @param $id
	 * @return void
	 */
	public function setId($id) {
		$this->setFileTypeID($id);
	}

	/**
	 * Alias method
	 *
	 * @return mixed
	 */
	public function getId() {
		return $this->getFileTypeID();
	}

	/**
	 * Set icon file
	 *
	 * @param $iconFile
	 */
	public function setIconFile($iconFile) {
		$this->iconFile = $iconFile;
	}

	/**
	 * Get icon file
	 *
	 * @return mixed
	 */
	public function getIconFile() {
		return $this->iconFile;
	}

	/**
	 * Set inline
	 *
	 * @param $inline
	 */
	public function setInline($inline) {
		$this->inline = $inline;
	}

	/**
	 * Get inline
	 *
	 * @return mixed
	 */
	public function getInline() {
		return $this->inline;
	}

	/**
	 * Set mime type
	 *
	 * @param $mime
	 */
	public function setMime($mime) {
		$this->mime = $mime;
	}

	/**
	 * Get mime type
	 *
	 * @return mixed
	 */
	public function getMime() {
		return $this->mime;
    }


    /**
     * Set max file size for mime type
     *
     * @param $maxFileSize
     * @return $this
     */
    public function setMaxFileSize($maxFileSize) {
        $this->maxFileSize = $maxFileSize;
        return $this;
    }

    /**
     * Get the max file size allowed for this particular mime type
     *
     * @return mixed
     */
    public function getMaxFileSize()  {
        return ($this->maxFileSize > 0) ? $this->maxFileSize : Config::get('max_file_size');
    }

    /**
     * Is mime type allowed
     * @return bool
     */
    public function isEnabled() {
        return $this->getEnable() ===  'true';
    }
}