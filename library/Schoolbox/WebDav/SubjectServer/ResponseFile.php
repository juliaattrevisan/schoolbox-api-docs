<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\WebDav\SubjectServer;

use Sabre\DAV;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\User;

/**
 * A DAV collection representing a single file within a submission.
 */
class ResponseFile extends DAV\File
{
//    /**
//     * @var User
//     */
//    protected $user;

    /**
     * @var Submission\SingleFile\Model
     */
    protected $responseFile;

    public function __construct(Submission\SingleFile\Model $responseFile)
    {
        $this->setResponseFile($responseFile);
    }

//    /**
//     * @param  User $user
//     *
//     * @return $this
//     */
//    public function setUser($user)
//    {
//        $this->user = $user;
//        return $this;
//    }
//
//    /**
//     * @return User
//     */
//    public function getUser()
//    {
//        return $this->user;
//    }

    /**
     * @param Submission\SingleFile\Model $responseFile
     *
     * @return $this
     */
    public function setResponseFile($responseFile)
    {
        $this->responseFile = $responseFile;
        return $this;
    }

    /**
     * @return Submission\SingleFile\Model
     */
    public function getResponseFile()
    {
        return $this->responseFile;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->getResponseFile()->getBasename();
    }

    /**
     * Returns the size of the file
     * @return int
     */
    public function getSize()
    {
        return $this->getResponseFile()->getStorage()->getSize();
    }

    /**
     * Returns the unique file key
     * @return string
     */
    public function getETag()
    {
        return $this->getResponseFile()->getStorage()->getHash();
    }

    /**
     * Returns the mimetype
     * @return string
     */
    public function getContentType()
    {
        return $this->getResponseFile()->getStorage()->getMime();
    }

    /**
     * Gets the time which this file was uploaded.
     *
     * @return int
     */
    public function getLastModified()
    {
        $submission = $this->getResponseFile();
        if ($submission instanceof Submission\SingleFile\Model) {
            $date = $submission->getCreatedAt();
            if ($date instanceof \Alaress_Date_Format && !$date->isNull()) {
                return $date->getUnixTimestamp();
            }
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
