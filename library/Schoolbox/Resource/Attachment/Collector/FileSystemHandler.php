<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Attachment\Collector;

use Schoolbox\Resource;
use Schoolbox\Resource\File;
use Schoolbox\Resource\Storage;

class FileSystemHandler implements TypeHandlerInterface
{

    /** @var File\ILocator */
    protected $locator;

    /** @var File\IStorageWrapper */
    protected $wrapper;


    /**
     * @param File\ILocator    $locator
     * @param File\IStorageWrapper $wrapper
     */
    public function __construct(File\ILocator $locator, File\IStorageWrapper $wrapper = null)
    {
        $this->locator = $locator;
        $this->wrapper = $wrapper;
    }


    /**
     * @param string $id
     * @throws Resource\Exception
     * @return Storage\IStorage
     */
    public function fetch(string $id): Storage\IStorage
    {
        // if receiver and locator are the same, just return a findById()
        if ($this->wrapper instanceof $this->locator) {
            return $this->locator->findById($id);
        }

        $storage = $this->locator->findById($id)->getStorage();

        if ($this->wrapper) {
            // get the storage object from the nominated file, and wrap that in the target class
            return $this->wrapper->wrap($storage);
        }

        return $storage;
    }
}
