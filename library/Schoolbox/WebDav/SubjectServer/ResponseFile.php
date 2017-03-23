<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\WebDav\SubjectServer;

use Sabre\DAV;
use Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as DueWorkResponseFileModel;
use Schoolbox\Learning\Assessment\DueWork\Submission\MapperProxy as DueWorkResponseDataSource;
use Schoolbox\Resource\Storage\Mapper as StorageDataSource;

/**
 * A DAV collection representing a single file within a submission.
 */
class ResponseFile extends DAV\File
{
    /**
     * @var DueWorkResponseFileModel
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
     * @param DueWorkResponseFileModel    $responseFile
     * @param StorageDataSource           $storageDataSource
     * @param DueWorkResponseDataSource   $dueWorkResponseDataSource
     */
    public function __construct(
        DueWorkResponseFileModel $responseFile,
        StorageDataSource $storageDataSource,
        DueWorkResponseDataSource $dueWorkResponseDataSource
    ) {
        $this->responseFile              = $responseFile;
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

//    /**
//     * Updates a file that has been created already.
//     * This is required for some WebDAV clients - the iPad Pages client sends an
//     * initial PUT request to create the file, and then sends another with the
//     * actual file contents.
//     * We'll need to have some sort of filtering on this, so that if you're
//     * trying to override a file you submitted previously it should fail.
//     *
//     * @throws Sabre\DAV\Exception\Forbidden if the file can't be updated
//     *
//     * @param resource $data
//     *
//     * @return null
//     */
//    public function put($data)
//    {
//
//        //
//        // Does this file belong to this student?
//        if ($this->getUser()->getId() != $this->getSubmission()->getOwner()->getId()) {
//            throw new Sabre\DAV\Exception\Forbidden('Cannot overwrite another student\'s submission');
//        }
//
//        //
//        // If the file was updated less than, say, 5 minutes ago, accept the
//        // request - otherwise, reject it.
//        $submit_time  = $this->getSubmission()->getCreatedAt()->getUnixTimestamp();
//        $current_time = $_SERVER['REQUEST_TIME'];
//        if (($current_time - $submit_time) > 300) {
//            throw new Sabre\DAV\Exception\Forbidden('Cannot overwrite an existing file with the same name');
//        }
//
//        //
//        // Load this file's contents.
//        $storage = Storage\Mapper::getInstance()->acceptStream($this->getName(), $data, false);
//
//        //
//        // And put it in the submission object.
//        $mapper = new Submission\SubmissionMapper();
//        $mapper->save($this->getSubmission()->setStorage($storage));
//        return null;
//    }
}
