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
use Config;
use League\Flysystem\File;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use RuntimeException;
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
class Model extends File implements IStorage, \Alaress\Json\Serializable, Linkable
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


    // File methods: all should be removed, as should the interface


    /**
     * @deprecated replace usages with `FilesystemProxy::fileExists`
     */
    public function exists()
    {
        return parent::exists();
    }

    /**
     * @deprecated replace usages with `FilesystemProxy::read`
     */
    public function read()
    {
        return parent::read();
    }

    /**
     * @deprecated replace usages with `FilesystemProxy::readStream`
     */
    public function readStream()
    {
        return parent::readStream();
    }

    /**
     * @deprecated replace usages with `FilesystemProxy::write`
     */
    public function write($content)
    {
        return parent::write($content);
    }

    /**
     * @deprecated replace usages with `FilesystemProxy::writeStream`
     */
    public function writeStream($resource)
    {
        return parent::writeStream($resource);
    }

    /**
     * @deprecated replace usages with `FilesystemProxy::write`
     */
    public function update($content)
    {
        return parent::update($content);
    }

    /**
     * @deprecated replace usages with `FilesystemProxy::writeStream`
     */
    public function updateStream($resource)
    {
        return parent::updateStream($resource);
    }

    /**
     * @deprecated replace usages with `FilesystemProxy::write`
     */
    public function put($content)
    {
        return parent::put($content);
    }

    /**
     * @deprecated replace usages with `FilesystemProxy::writeStream`
     */
    public function putStream($resource)
    {
        return parent::putStream($resource);
    }

    /**
     * @deprecated remove usages
     */
    public function rename($newpath)
    {
        return parent::rename($newpath);
    }

    /**
     * @deprecated remove usages
     */
    public function copy($newpath)
    {
        return parent::copy($newpath);
    }

    /**
     * @deprecated replace usages with `FilesystemProxy::lastModified`
     */
    public function getTimestamp()
    {
        return parent::getTimestamp();
    }

    /**
     * @deprecated replace usages with `FilesystemProxy::visibility`
     */
    public function getVisibility()
    {
        return parent::getVisibility();
    }

    /**
     * @deprecated remove usages
     */
    public function getMetadata()
    {
        return parent::getMetadata();
    }

    /**
     * @deprecated replace usages with `FilesystemProxy::delete`
     */
    public function delete()
    {
        return parent::delete();
    }


    // Handler methods: all must be removed

    /**
     * @deprecated remove this constructor, use 0 parameter constructor
     */
    public function __construct(FilesystemInterface $filesystem = null, $path = null)
    {
        parent::__construct($filesystem, $path);
    }

    /**
     * @deprecated remove usages, use filesystem proxy instead
     */
    public function setFilesystem(FilesystemInterface $filesystem)
    {
        return parent::setFilesystem($filesystem);
    }

    /**
     * @deprecated remove usages, use filesystem proxy instead
     */
    public function getFilesystem()
    {
        return parent::getFilesystem();
    }

    /**
     * @deprecated remove usages
     */
    public function setPath($path)
    {
        return parent::setPath($path);
    }

    /**
     * @deprecated remove usages
     */
    public function getPath()
    {
        return parent::getPath();
    }


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
        // Calculate hash if not known

        if (!$this->hash) {
            // Don't calc it if no path

            if (!$this->path) {
                return null;
            }

            // TODO hash generator should be injected
            $this->setHash((new HashGenerator())->generateHash($this->readStream()));
        }

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
     * @param bool $detect
     *
     * @return string
     */
    public function getMime($detect = false)
    {
        if ($detect) {
            return parent::getMimetype();
        }

        if ($this->getMimetype() instanceof Mimetype) {
            return $this->getMimetype()->getMime();
        } else {
            return '';
        }
    }

    /**
     * @return int
     * @throws FileNotFoundException
     */
    public function getSize()
    {
        // Calculate size if not known

        if (!$this->size) {
            try {
                $this->setSize(parent::getSize());
            } catch (RuntimeException $e) {
                throw new FileNotFoundException($this->getPath(), 0, $e);
            }
        }

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
     * Pipe the readstream of this file to some other stream resource
     *
     * @param resource $to
     *
     * @return int
     */
    public function pipe($to)
    {
        $from = $this->readStream();

        $size = 0;

        while (!feof($from)) {
            $size += fwrite($to, fread($from, 8192));
        }

        return $size;
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


    /**
     * Serialize everything except the filesystem.
     *
     * This will mean unserialized files cannot have any filesystem-related
     * operations performed on them, until `setFilesystem` is called on them.
     *
     * @see Mapper::getManager(), Manager::getFilesystem()
     *
     * @deprecated remove once this no longer inherits File
     *
     * @return array
     */
    public function __sleep()
    {
        $vars = get_class_vars(get_class($this));
        unset($vars['filesystem']);
        return array_keys($vars);
    }


    /**
     * On wakeup, resurrect the filesystem that was not serialized in `__sleep`.
     *
     * @deprecated remove once this no longer inherits File
     */
    public function __wakeup()
    {
        // TODO this really should not hit config. We don't even know if config exists at this point
        $this->filesystem = (new FilesystemFactory())
            ->build(Config::get('storage_dir'))
            ->getFilesystem(Manager::FS_STORAGE);
    }
}
