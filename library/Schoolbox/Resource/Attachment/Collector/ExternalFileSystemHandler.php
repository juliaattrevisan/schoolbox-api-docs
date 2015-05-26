<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Attachment\Collector;


use Alaress\File\Remote\FetcherInterface;
use Schoolbox\Resource\File;
use Schoolbox\Resource;

class ExternalFileSystemHandler extends FileSystemHandler {

    /** @var FetcherInterface */
    protected $fetcher;

    /** @var Resource\Storage\Receiver */
    protected $receiver;


    /**
     * @param File\ILocator             $locator
     * @param Resource\Storage\Receiver $receiver
     * @param FetcherInterface          $fetcher
     * @param File\IStorageWrapper      $wrapper
     */
    public function __construct(
        File\ILocator $locator,
        Resource\Storage\Receiver $receiver,
        FetcherInterface $fetcher,
        File\IStorageWrapper $wrapper = null
    ) {
        parent::__construct($locator, $wrapper);
        $this->receiver = $receiver;
        $this->fetcher  = $fetcher;
    }


    /**
     * @param string $id
     * @throws Resource\Exception
     * @return Resource\Storage\IStorage
     */
    public function fetch($id)
    {
        $file = $this->locator->findById($id);

        if (!$file instanceof File\IFile) {
            throw new Resource\Exception("Invalid type: resource is folder, not file", Resource\Exception::CODE_UNKNOWN);
        }

        $tempFilename = $this->fetcher->fetchToFile($file->getId());
        $basename     = $file->getBasename();

        $storage = $this->receiver->accept($basename, $tempFilename, false);

        if ($this->wrapper) {
            return $this->wrapper->wrap($storage);
        }

        return $storage;
    }

}