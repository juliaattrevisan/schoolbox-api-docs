<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\WebDav\SimpleSubjectServer;

use Sabre\DAV;
use Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as DueWorkResponseFile;

/**
 * A DAV collection representing a single file within a submission.
 */
class ResponseFile extends DAV\File
{
    /**
     * @var DueWorkResponseFile
     */
    private $responseFile;

    /**
     * Constructor
     *
     * @param DueWorkResponseFile $responseFile
     */
    public function __construct(DueWorkResponseFile $responseFile)
    {
        $this->responseFile = $responseFile;
    }

    /**
     * @return resource
     */
    public function get()
    {
        return $this->responseFile->readStream();
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->responseFile->getBasename();
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
