<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\WebDav\SubjectServer;

use Sabre\DAV;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as DueWorkResponseFile;
use Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkResponse;
use Schoolbox\Learning\Assessment\DueWork\Submission\MapperProxy as DueWorkResponseDataSource;
use Schoolbox\Resource\Storage\Mapper as StorageDataSource;

/**
 * A DAV collection representing a single file within a submission.
 */
class ResponseFile extends DAV\File
{
    /**
     * @var DueWorkResponse
     */
    private $response;

    /**
     * @var DueWorkResponseFile
     */
    private $responseFile;

    /**
     * @var StorageDataSource
     */
    private $storageDataSource;

    /**
     * @var DueWorkResponseDataSource
     */
    private $dueWorkResponseDataSource;

    /**
     * Constructor
     *
     * @param DueWorkResponseFile       $responseFile
     * @param DueWorkResponse           $response
     * @param StorageDataSource         $storageDataSource
     * @param DueWorkResponseDataSource $dueWorkResponseDataSource
     */
    public function __construct(
        DueWorkResponseFile $responseFile,
        DueWorkResponse $response,
        StorageDataSource $storageDataSource,
        DueWorkResponseDataSource $dueWorkResponseDataSource
    ) {
        $this->responseFile              = $responseFile;
        $this->response                  = $response;
        $this->storageDataSource         = $storageDataSource;
        $this->dueWorkResponseDataSource = $dueWorkResponseDataSource;
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
