<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Attachment\Collector;


use Schoolbox\Resource\Storage;
use Schoolbox\Resource\File;

class StorageHandler implements TypeHandlerInterface {

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
    public function fetch($id)
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