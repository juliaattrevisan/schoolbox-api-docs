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

    /** @var File\IReceiver */
    protected $receiver;

    /**
     * @param Storage\Mapper $mapper
     * @param File\IReceiver $receiver
     */
    public function __construct(Storage\Mapper $mapper, File\IReceiver $receiver = null)
    {
        $this->mapper = $mapper;
        $this->receiver = $receiver;
    }


    /**
     * @param string $id
     * @return File\IFile
     */
    public function fetch($id)
    {
        // storage file has already been uploaded, so retrieve it
        $storage = $this->mapper->findByHash($id);

        if ($this->receiver) {
            // the receiver needs to take the storage object and return some IFile
            return $this->receiver->wrap($storage);
        }

        // TODO fix this interface breach :/
        return $storage;
    }

}