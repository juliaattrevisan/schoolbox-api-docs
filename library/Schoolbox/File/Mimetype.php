<?php

/**
 * Mimetype
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\File;

use Config;
use Schoolbox\ISoftDeletable;

/**
 * Class Mimetype
 * @package Schoolbox\File
 */
class Mimetype implements ISoftDeletable
{
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

    /** @var bool */
    protected $deleted;

    /**
     * Get icon class by deriving it from the iconFile
     *
     * This previously abused the \Url class, but now it's all self-contained here and independent.
     *
     * @return string
     */
    public function getClass()
    {
        return preg_replace('/[^a-zA-Z]/', '', substr(strtolower($this->getIconFile()), 0, strrpos($this->getIconFile(), ".")));
    }

    /* Getters/Setters
     */

    /**
     * Set description
     *
     * @param string $description
     * @return $this
     */
    public function setDescription($description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set enable
     *
     * @param bool $enable
     * @return $this
     */
    public function setEnable($enable): self
    {
        $this->enable = $enable;
        return $this;
    }

    /**
     * Get enable
     *
     * @return bool
     */
    public function getEnable()
    {
        return $this->enable;
    }

    /**
     * Set file type id
     *
     * @param int $fileTypeID
     * @return $this
     */
    public function setFileTypeID($fileTypeID): self
    {
        $this->fileTypeID = $fileTypeID;
        return $this;
    }

    /**
     * Get file type id
     *
     * @return int
     */
    public function getFileTypeID()
    {
        return $this->fileTypeID;
    }

    /**
     * Alias method
     *
     * @param int $id
     * @return $this
     */
    public function setId($id): self
    {
        $this->setFileTypeID($id);
        return $this;
    }

    /**
     * Alias method
     *
     * @return int
     */
    public function getId()
    {
        return $this->getFileTypeID();
    }

    /**
     * Set icon file
     *
     * @param string $iconFile
     * @return $this
     */
    public function setIconFile($iconFile): self
    {
        $this->iconFile = $iconFile;
        return $this;
    }

    /**
     * Get icon file
     *
     * @return string
     */
    public function getIconFile()
    {
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
     * @return $this
     */
    public function setInline($inline): self
    {
        $this->inline = $inline;
        return $this;
    }

    /**
     * Get inline
     *
     * @return bool
     */
    public function getInline()
    {
        return $this->inline;
    }

    /**
     * Set mime type
     *
     * @param string $mime
     * @return $this
     */
    public function setMime($mime): self
    {
        $this->mime = $mime;
        return $this;
    }

    /**
     * Get mime type
     *
     * @return string|null
     */
    public function getMime()
    {
        return $this->mime;
    }


    /**
     * Set max file size for mime type
     *
     * @param int $maxFileSize
     * @return $this
     */
    public function setMaxFileSize($maxFileSize): self
    {
        $this->maxFileSize = $maxFileSize;
        return $this;
    }

    /**
     * Get the max file size allowed for this particular mime type.
     *
     * If the max file size for this mimetype is empty, this returns the global
     * default max file size.
     *
     * @return int
     */
    public function getActualMaxFileSize()
    {
        return ($this->maxFileSize > 0) ? $this->maxFileSize : Config::get('max_file_size');
    }

    /**
     * Get the max file size that is stored for this mimetype.
     *
     * Unlike `getActualMaxFileSize()`, this does not return the global default max
     * file size if this mimetype has none.
     *
     * @return int
     */
    public function getMaxFileSize()
    {
        return $this->maxFileSize;
    }

    /**
     * Is mime type allowed
     * @return bool
     */
    public function isEnabled()
    {
        return $this->getEnable();
    }


    /**
     * @deprecated mapper compat method
     * @return bool
     */
    public function getDeleted()
    {
        return $this->isDeleted();
    }

    /**
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->deleted;
    }


    /**
     * @param boolean $deleted
     * @return $this
     */
    public function setDeleted($deleted): self
    {
        $this->deleted = $deleted;
        return $this;
    }

    /**
     * Is this file streamable? (Audio and video are streamable, others are not)
     *
     * @return bool
     */
    public function isStreamable(): bool
    {
        return $this->isAudio() || $this->isVideo();
    }


    /**
     * Determine if a file is audio
     *
     * @return bool
     */
    public function isAudio()
    {
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
    public function isVideo()
    {
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
    public function isImage()
    {
        if (preg_match('#^image/#', $this->getMime())) {
            return true;
        }

        return false;
    }


    /**
     * Decide whether this is image, video, audio, documents or other files
     * encompassing separate filetypes for each
     */
    public function getType(): ?string
    {
        //new file / mime object, so return null
        if (null === $this->getMime()) {
            return null;
        }

        //is a file, lets check which it is
        if ($this->isImage()) {
            return 'image';
        }
        if ($this->isAudio()) {
            return 'audio';
        }
        if ($this->isVideo()) {
            return 'video';
        }
        if (preg_match('/(pdf)|(msword)|(vnd.ms)|(rtf)|(text)|(document)|(powerpoint)/', $this->getMime())) {
            return 'document';
        }

        //if not a 'standard' document, it is something else, return other
        return 'other';
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
            $filter = str_replace('*', '[^/]+', $filter);

            //preg_quote escapes characters like "+" in the mimetype (e.g. image/svg+xml)
            if (preg_match("`^" . preg_quote($filter) . "$`", $this->getMime())) {
                return true;
            }
        }

        return false;
    }


    /**
     * @return string
     */
    public function __toString()
    {
        return (string)$this->getMime();
    }
}
