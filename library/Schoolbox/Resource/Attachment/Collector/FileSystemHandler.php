<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Attachment\Collector;

use Schoolbox\Resource;
use Schoolbox\Resource\FileLocatorInterface;
use Schoolbox\Storage;
use Schoolbox\StorageMapper;

class FileSystemHandler implements TypeHandlerInterface {

    /** @var string */
    protected $prefix;

    /** @var FileLocatorInterface */
    protected $locator;

    /** @var StorageMapper */
    protected $storage;

    /** @var string */
    protected $dir;

    /**
     * @param string               $prefix
     * @param string               $dir
     * @param FileLocatorInterface $locator
     * @param StorageMapper        $storage
     */
    public function __construct($prefix, $dir, FileLocatorInterface $locator, StorageMapper $storage)
    {
        $this->prefix  = $prefix . '.';
        $this->dir     = $dir;
        $this->locator = $locator;
        $this->storage = $storage;
    }


    /**
     * @param string $id
     * @return bool
     */
    public function matches($id)
    {
        return strpos($id, $this->prefix) === 0;
    }


    /**
     * @param string $name
     * @return string
     */
    protected function extractFileId($name)
    {
        return substr($name, strlen($this->prefix) + 3);
    }


    /**
     * TODO for now, we are simply copying resources from SBFS to Storage. Upgrade by intelligently linking through to storage
     *
     * @param string $id
     * @throws \Schoolbox\Resource\Exception
     * @return Storage
     */
    public function fetch($id)
    {
        $file = $this->locator->findById($this->extractFileId($id));

        $pattern  = $this->dir . '/%d.dat';
        $filename = $file->getFilename();
        $location = sprintf($pattern, $file->getId());
        $owner    = $file->getOwner()->getId();

        try {
            return $this->storage->upload($filename, $location, $owner);
        } catch (\PDOException $e) {
            throw new Resource\Exception("Could not copy file", Resource\Exception::CODE_COPY_TO_STORAGE_FAILED, $e);
        }
    }

}