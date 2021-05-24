<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Attachment\Collector;

use Schoolbox\Resource\Storage;
use Schoolbox\Resource\File;

class StorageHandler implements TypeHandlerInterface
{

    /** @var Storage\Mapper */
    protected $mapper;

    /** @var File\IStorageWrapper */
    protected $wrapper;


    /**
     * @param Storage\Mapper $mapper
     * @param File\IStorageWrapper $wrapper
     */
    public function __construct(Storage\Mapper $mapper, File\IStorageWrapper $wrapper = null)
    {
        $this->mapper   = $mapper;
        $this->wrapper = $wrapper;
    }


    /**
     * @param string $id
     * @return Storage\IStorage
     */
    public function fetch(string $id): Storage\IStorage
    {
        // storage file has already been uploaded, so retrieve it
        $storage = $this->mapper->findByHash($id);

        if ($this->wrapper) {
            // the receiver needs to take the storage object and return some IFile
            return $this->wrapper->wrap($storage);
        }

        return $storage;
    }
}
