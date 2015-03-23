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
use Schoolbox\FileInterface;
use Schoolbox\Resource\File;
use Schoolbox\Resource;

class ExternalFileSystemHandler extends FileSystemHandler {

    /** @var FetcherInterface */
    protected $fetcher;

    /** @var File\IFileMapper */
    protected $receiver;


    /**
     * @param File\ILocator    $locator
     * @param File\IFileMapper $fileMapper
     * @param FetcherInterface $fetcher
     */
    public function __construct(File\ILocator $locator, File\IFileMapper $fileMapper, FetcherInterface $fetcher)
    {
        parent::__construct($locator, $fileMapper);
        $this->fetcher = $fetcher;
    }


    /**
     * @param string $id
     * @throws Resource\Exception
     * @return FileInterface
     */
    public function fetch($id)
    {
        $file = $this->locator->findById($id);

        if (!$file instanceof File\IFile) {
            throw new Resource\Exception("Invalid type: resource is folder, not file", Resource\Exception::CODE_UNKNOWN);
        }

        $tempFilename = $this->fetcher->fetchToFile($file->getId());
        $filename     = $file->getFilename();

        $storage = $this->receiver->getStorageMapper()->accept($filename, $tempFilename, false);

        return $this->receiver->wrap($storage);
    }

}