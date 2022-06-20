<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Attachment\Collector;

use Alaress\File\Remote\FetcherInterface;
use Schoolbox\Resource\File;
use Schoolbox\Resource;

class ExternalFileSystemHandler extends FileSystemHandler
{

    /** @var FetcherInterface */
    protected $fetcher;

    /** @var Resource\Storage\IReceiver */
    protected $receiver;


    /**
     * @param File\ILocator              $locator
     * @param Resource\Storage\IReceiver $receiver
     * @param FetcherInterface           $fetcher
     * @param File\IStorageWrapper       $wrapper
     */
    public function __construct(
        File\ILocator $locator,
        Resource\Storage\IReceiver $receiver,
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
    public function fetch(string $id): Resource\Storage\IStorage
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
