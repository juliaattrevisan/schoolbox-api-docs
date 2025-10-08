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
use Schoolbox\Resource\Attachment\Zone;

/**
 * Class Mimetype
 * @package Schoolbox\File
 */
class Mimetype implements ISoftDeletable
{
    /**
     * Allowed File id
     *
     * @var int|null
     */
    protected ?int $fileTypeID = null;

    /**
     * Description for this mime type
     *
     * @var string|null
     */
    protected ?string $description = null;

    /**
     * Mime type
     *
     * @var string|null
     */
    protected ?string $mime = null;

    /**
     * Filename representing this mime type
     *
     * @var string|null
     */
    protected ?string $iconFile = null;

    /**
     * String true/false depending on whether this allowed filetype is enabled
     * (that is, allowed to be uploaded)
     *
     * @var bool|null
     */
    protected ?bool $enable = null;

    /**
     * Max file size allowed for mime
     *
     * @var int|null
     */
    protected ?int $maxFileSize = null;

    /** @var bool */
    protected ?bool $deleted = null;

    /**
     * Get icon class by deriving it from the iconFile
     *
     * This previously abused the \Url class, but now it's all self-contained here and independent.
     *
     * @return string
     */
    public function getClass(): string
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
    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get description
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Set enable
     *
     * @param bool $enable
     * @return $this
     */
    public function setEnable(bool $enable): self
    {
        $this->enable = $enable;
        return $this;
    }

    /**
     * Get enable
     *
     * @return bool|null
     */
    public function getEnable(): ?bool
    {
        return $this->enable;
    }

    /**
     * Set file type id
     *
     * @param int $fileTypeID
     * @return $this
     */
    public function setFileTypeID(int $fileTypeID): self
    {
        $this->fileTypeID = $fileTypeID;
        return $this;
    }

    /**
     * Get file type id
     *
     * @return int|null
     */
    public function getFileTypeID(): ?int
    {
        return $this->fileTypeID;
    }

    /**
     * Alias method
     *
     * @param int $id
     * @return $this
     */
    public function setId(int $id): self
    {
        $this->setFileTypeID($id);
        return $this;
    }

    /**
     * Alias method
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->getFileTypeID();
    }

    /**
     * Set icon file
     *
     * @param string $iconFile
     * @return $this
     */
    public function setIconFile(string $iconFile): self
    {
        $this->iconFile = $iconFile;
        return $this;
    }

    /**
     * Get icon file
     *
     * @return string|null
     */
    public function getIconFile(): ?string
    {
        return $this->iconFile;
    }

    /**
     * Gets the CSS class for this icon.
     *
     * @return string|null
     */
    public function getIconClass(): ?string
    {
        // Old code in `AllowedFile->getClass()`
//        $url = new \Url(substr($iconFile, 0, strrpos($iconFile, ".")));
//        return $url->getSlug();
        return str_replace('_', '', pathinfo($this->getIconFile(), PATHINFO_FILENAME));
    }

    /**
     * Set mime type
     *
     * @param string $mime
     * @return $this
     */
    public function setMime(string $mime): self
    {
        $this->mime = $mime;
        return $this;
    }

    /**
     * Get mime type
     *
     * @return string|null
     */
    public function getMime(): ?string
    {
        return $this->mime;
    }


    /**
     * Set max file size for mime type
     *
     * @param int $maxFileSize
     * @return $this
     */
    public function setMaxFileSize(int $maxFileSize): self
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
    public function getActualMaxFileSize(): int
    {
        return ($this->maxFileSize > 0) ? $this->maxFileSize : (int)Config::get('max_file_size');
    }

    /**
     * Get the max file size that is stored for this mimetype.
     *
     * Unlike `getActualMaxFileSize()`, this does not return the global default max
     * file size if this mimetype has none.
     *
     * @return int|null
     */
    public function getMaxFileSize(): ?int
    {
        return $this->maxFileSize;
    }

    /**
     * Is mime type allowed
     *
     * @return bool|null
     */
    public function isEnabled(): ?bool
    {
        return $this->getEnable();
    }


    /**
     * @deprecated mapper compat method
     *
     * @return bool|null
     */
    public function getDeleted(): ?bool
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
     * @param bool $deleted
     * @return $this
     */
    public function setDeleted(bool $deleted): self
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
    public function isAudio(): bool
    {
        return str_starts_with($this->getMime(), 'audio/');
    }

    /**
     * Determine if a file is video
     *
     * @return bool
     */
    public function isVideo(): bool
    {
        return str_starts_with($this->getMime(), 'video/');
    }

    /**
     * Determine if a file is an image
     *
     * @return bool
     */
    public function isImage(): bool
    {
        return str_starts_with($this->getMime(), 'image/');
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
     * @param string|null $mimeFilter in the format:
     *                           "image/jpeg,image/png", "image/*" or "{star}/*"
     * @return bool
     */
    public function matches(?string $mimeFilter): bool
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

            $mime = $this->getMime() ?? '';

            // wildcard subtype match, e.g.:
            // * "image/*" against any image mimetype
            // * "application/vnd.*" against any vendor mimetype
            if (preg_match('`^(?P<type>.*?)\\*$`', $filter, $matches)) {
                if (str_starts_with($mime, $matches['type'])) {
                    return true;
                }
            }

            // wildcard type match, e.g.:
            // * "*/mpeg" against any type of mpeg
            if (preg_match('`^\\*(?P<subtype>.*)$`', $filter, $matches)) {
                if (str_ends_with($mime, $matches['subtype'])) {
                    return true;
                }
            }

            // harder comparison:
            // could probably use an exact comparison instead, but this method
            // is likely used in ways I don't know about yet.
            // At least there's unit tests to cover it now.
            $filter = str_replace('*', '[^/]+', $filter);
            $regex  = '/^' . preg_quote($filter, '/') . '$/';
            if (preg_match($regex, $mime)) {
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
