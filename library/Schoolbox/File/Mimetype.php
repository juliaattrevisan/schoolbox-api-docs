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
	 * @var int
	 */
	protected $fileTypeID;

	/**
	 * Description for this mime type
	 *
	 * @var string
	 */
	protected $description;

	/**
	 * Mime type
	 *
	 * @var string
	 */
	protected $mime;

	/**
	 * Filename representing this mime type
	 *
	 * @var string
	 */
	protected $iconFile;

	/**
	 * String true/false depending on whether the filetype can be opened
	 * inline within the browser
	 *
	 * @var bool
	 */
	protected $inline;

	/**
	 * String true/false depending on whether this allowed filetype is enabled
	 * (that is, allowed to be uploaded)
	 *
	 * @var bool
	 */
	protected $enable;

    /**
     * Max file size allowed for mime
     *
     * @var int
     */
    protected $maxFileSize;

    /**
	 * Get icon class by deriving it from the iconFile
	 *
	 * This previously abused the \Url class, but now it's all self-contained here and independent.
	 *
	 * @return string
	 */
	public function getClass() {
		return preg_replace('/[^a-zA-Z]/', '', substr(strtolower($this->getIconFile()), 0, strrpos($this->getIconFile(), ".")));
	}

	/* Getters/Setters
	 */

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
	 * Set enable
	 *
	 * @param bool $enable
	 * @return void
	 */
	public function setEnable($enable) {
		$this->enable = $enable;
	}

	/**
	 * Get enable
	 *
	 * @return bool
	 */
	public function getEnable() {
		return $this->enable;
	}

	/**
	 * Set file type id
	 *
	 * @param int $fileTypeID
	 * @return void
	 */
	public function setFileTypeID($fileTypeID) {
		$this->fileTypeID = $fileTypeID;
	}

	/**
	 * Get file type id
	 *
	 * @return int
	 */
	public function getFileTypeID() {
		return $this->fileTypeID;
	}

	/**
	 * Alias method
	 *
	 * @param int $id
	 * @return void
	 */
	public function setId($id) {
		$this->setFileTypeID($id);
	}

	/**
	 * Alias method
	 *
	 * @return int
	 */
	public function getId() {
		return $this->getFileTypeID();
	}

	/**
	 * Set icon file
	 *
	 * @param string $iconFile
	 */
	public function setIconFile($iconFile) {
		$this->iconFile = $iconFile;
	}

	/**
	 * Get icon file
	 *
	 * @return string
	 */
	public function getIconFile() {
		return $this->iconFile;
	}

    /**
     * Gets the CSS class for this icon.
     *
     * @return string
     */
    public function getIconClass()
    {
        // Old code in `AllowedFile->getClass()`
//        $url = new \Url(substr($iconFile, 0, strrpos($iconFile, ".")));
//        return $url->getSlug();
        return str_replace('_', '', pathinfo($this->getIconFile(), PATHINFO_FILENAME));
    }

	/**
	 * Set inline
	 *
	 * @param bool $inline
	 */
	public function setInline($inline) {
		$this->inline = $inline;
	}

	/**
	 * Get inline
	 *
	 * @return bool
	 */
	public function getInline() {
		return $this->inline;
	}

	/**
	 * Set mime type
	 *
	 * @param string $mime
	 */
	public function setMime($mime) {
		$this->mime = $mime;
	}

	/**
	 * Get mime type
	 *
	 * @return string
	 */
	public function getMime() {
		return $this->mime;
    }


    /**
     * Set max file size for mime type
     *
     * @param int $maxFileSize
     * @return $this
     */
    public function setMaxFileSize($maxFileSize) {
        $this->maxFileSize = $maxFileSize;
        return $this;
    }

    /**
     * Get the max file size allowed for this particular mime type
     *
     * @return int
     */
    public function getMaxFileSize()  {
        return ($this->maxFileSize > 0) ? $this->maxFileSize : Config::get('max_file_size');
    }

    /**
     * Is mime type allowed
     * @return bool
     */
    public function isEnabled() {
        return $this->getEnable();
    }

    /**
     * Determine if a file is audio
     *
     * @return bool
     */
    public function isAudio() {
        if (preg_match('#^audio/#', $this->getMime())) {
            return true;
        }

        return false;
    }

    /**
     * Determine if a file is video
     *
     * @return bool
     */
    public function isVideo() {
        if (preg_match('#^video/#', $this->getMime())) {
            return true;
        }

        return false;
    }

    /**
     * Determine if a file is an image
     *
     * @return bool
     */
    public function isImage() {
        if (preg_match('#^image/#', $this->getMime())) {
            return true;
        }

        return false;
    }


    /**
     * Compares this mimetype against the given filter. Returns true if ANY are matched.
     *
     * @param string $mimeFilter in the format:
     *                           "image/jpeg,image/png", "image/*" or "{star}/*"
     * @return bool
     */
    public function matches($mimeFilter)
    {
        // if the filter can loosely evaluate to false, it's considered no filter therefore match
        if (!($mimeFilter)) {
            return true;
        }

        $xpl = explode(',', $mimeFilter);

        foreach ($xpl as $filter) {

            // easy comparison
            if ($filter === '*/*') {
                return true;
            }

            // harder comparison
            $filter = str_replace('*','[^/]+', $filter);

            if (preg_match("`^$filter$`", $this->getMime())) {
                return true;
            }

        }

        return false;
    }


    /**
     * @return string
     */
    function __toString()
    {
        return (string)$this->getMime();
    }

}