<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Storage;

use Alaress\Json\Linkable;
use Alaress\Json\SerializeTrait;
use Alaress_Date_Format;
use BadMethodCallException;
use Config;
use Schoolbox\File\Mimetype;
use Schoolbox\Json\Api\SitePageLink;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Resource\FilesystemFactory;

/**
 * Class Model
 * Not explicitly a "Schoolbox file entity".
 * This object ONLY represents the file on disk, and needs to be wrapped in other classes for actual Schoolbox
 * functionality.
 *
 * @package Schoolbox\Resource\Storage
 */
class Model implements IStorage, \Alaress\Json\Serializable, Linkable
{
    use CreatedAtDate;
    use SerializeTrait;

    /** @var string $filename */
    protected $filename;

    /** @var string $hash */
    protected $hash;

    /** @var Mimetype $mimetype */
    protected $mimetype;

    /** @var int $size */
    protected $size;

    /** @var Alaress_Date_Format $lastAccessed */
    protected $lastAccessed;

    /** @var bool $public */
    protected $public;

    /** @var string $extension */
    protected $extension;


    /**
     * @var Alaress_Date_Format|null
     */
    protected ?\Alaress_Date_Format $expiryAt = null;

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @param string $filename
     *
     * @return static
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;

        return $this;
    }

    /**
     * @return string
     */
    public function getBasename()
    {
        return $this->getFilename() . '.' . $this->getExtension();
    }

    /**
     * @param string $basename
     *
     * @return $this
     */
    public function setBasename($basename)
    {
        return $this
            ->setFilename(mb_pathinfo($basename, PATHINFO_FILENAME))
            ->setExtension(mb_pathinfo($basename, PATHINFO_EXTENSION));
    }

    /**
     * @return string
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * @param string $hash
     *
     * @return self
     */
    public function setHash($hash)
    {
        $this->hash = $hash;

        return $this;
    }

    /**
     * @return Mimetype
     */
    public function getMimetype()
    {
        return $this->mimetype;
    }

    /**
     * @param Mimetype $mimetype
     *
     * @return self
     */
    public function setMimetype(Mimetype $mimetype)
    {
        $this->mimetype = $mimetype;

        return $this;
    }

    /**
     * Returns the full mime string for the file
     *
     * TODO check usages to verify this is not called with detect = true
     *
     * @return string
     */
    public function getMime()
    {
        if ($this->getMimetype() instanceof Mimetype) {
            return $this->getMimetype()->getMime();
        } else {
            return '';
        }
    }

    /**
     * @return int
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @param int $size
     *
     * @return self
     */
    public function setSize($size)
    {
        $this->size = $size;

        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getLastAccessed()
    {
        return $this->lastAccessed;
    }

    /**
     * @param Alaress_Date_Format $lastAccessed
     *
     * @return self
     */
    public function setLastAccessed($lastAccessed)
    {
        $this->lastAccessed = $lastAccessed;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isPublic()
    {
        return $this->public;
    }

    /**
     * @deprecated mapper compat
     * @return bool
     */
    public function getPublic()
    {
        return $this->isPublic();
    }

    /**
     * @param boolean $public
     *
     * @return self
     */
    public function setPublic($public)
    {
        $this->public = $public;

        return $this;
    }

    /**
     * @return string
     */
    public function getExtension()
    {
        return $this->extension;
    }

    /**
     * @param string $extension
     *
     * @return self
     */
    public function setExtension($extension)
    {
        $this->extension = $extension;

        return $this;
    }


    /**
     * @return Alaress_Date_Format|null
     */
    public function getExpiryAt(): ?Alaress_Date_Format
    {
        return $this->expiryAt;
    }


    /**
     * @param Alaress_Date_Format|null $expiryAt
     * @return $this
     */
    public function setExpiryAt(?Alaress_Date_Format $expiryAt): self
    {
        $this->expiryAt = $expiryAt;
        return $this;
    }


    /**
     * @return array
     */
    public function getDefaultJsonMap(): array
    {
        return [
            'hash',
            'size',
            'mime'
        ];
    }


    /**
     * @return array
     */
    public function getJsonApiLinks(): array
    {
        return [
            'fetch' => new SitePageLink("/storage/fetch.php?hash={$this->getHash()}"),
            'image' => new SitePageLink("/storage/image.php?hash={$this->getHash()}"),
        ];
    }
}
