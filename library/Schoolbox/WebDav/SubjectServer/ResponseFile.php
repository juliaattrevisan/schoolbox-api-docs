<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\WebDav\SubjectServer;

use Sabre\DAV;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as DueWorkResponseFile;
use Schoolbox\Resource\Storage\FilesystemProxy;

/**
 * A DAV collection representing a single file within a submission.
 */
class ResponseFile extends DAV\File
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var DueWorkResponseFile
     */
    private $responseFile;

    /**
     * Constructor
     *
     * @param string              $name Usually the file's name, but can be changed to be made unique
     * @param DueWorkResponseFile $responseFile
     * @param FilesystemProxy     $filesystemProxy
     */
    public function __construct(
        $name,
        DueWorkResponseFile $responseFile,
        private FilesystemProxy $filesystemProxy,
    ) {
        $this->name         = $name;
        $this->responseFile = $responseFile;
    }

    /**
     * @return resource
     */
    public function get()
    {
        return $this->filesystemProxy->readStream($this->responseFile);
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the size of the file
     * @return int
     */
    public function getSize()
    {
        return $this->responseFile->getStorage()->getSize();
    }

    /**
     * Returns the unique file key
     * @return string
     */
    public function getETag()
    {
        return $this->responseFile->getStorage()->getHash();
    }

    /**
     * Returns the mimetype
     * @return string
     */
    public function getContentType()
    {
        return $this->responseFile->getStorage()->getMime();
    }

    /**
     * Gets the time which this file was uploaded.
     *
     * @return int
     */
    public function getLastModified()
    {
        $date = $this->responseFile->getCreatedAt();
        if ($date instanceof \Alaress_Date_Format && !$date->isNull()) {
            return $date->getUnixTimeStamp();
        }
        return time();
    }
}
