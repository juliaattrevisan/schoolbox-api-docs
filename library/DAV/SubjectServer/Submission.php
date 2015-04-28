<?php
/**
 * A DAV collection representing a single submission within an assessment.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\Resource\Storage;
use Schoolbox\User;

class DAV_SubjectServer_Submission extends Sabre\DAV\SimpleFile
{

    /**
     * @var User
     */
    protected $user;

    /**
     * @var Submission\File
     */
    protected $submission;

    /**
     * @param  User $user
     *
     * @return $this
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param Submission\File $submission
     *
     * @return $this
     */
    public function setSubmission($submission)
    {
        $this->submission = $submission;
        return $this;
    }

    /**
     * @return Submission\File
     */
    public function getSubmission()
    {
        return $this->submission;
    }

    /**
     * Returns the size of the file
     * @return int
     */
    public function getSize()
    {
        return $this->getSubmission()->getStorage()->getSize();
    }

    /**
     * Returns the unique file key
     * @return string
     */
    public function getETag()
    {
        return $this->getSubmission()->getStorage()->getHash();
    }

    /**
     * Returns the mimetype
     * @return string
     */
    public function getContentType()
    {
        return $this->getSubmission()->getStorage()->getMime();
    }

    /**
     * Gets the time which this file was uploaded.
     *
     * @return int
     */
    public function getLastModified()
    {
        $submission = $this->getSubmission();
        if ($submission instanceof Submission\File) {
            $date = $submission->getCreatedAt();
            if ($date instanceof Alaress_Date_Format) {
                return $date->getUnixTimestamp();
            }
        }
        return time();
    }

    /**
     * Updates a file that has been created already.
     * This is required for some WebDAV clients - the iPad Pages client sends an
     * initial PUT request to create the file, and then sends another with the
     * actual file contents.
     * We'll need to have some sort of filtering on this, so that if you're
     * trying to override a file you submitted previously it should fail.
     *
     * @throws Sabre\DAV\Exception\Forbidden if the file can't be updated
     *
     * @param resource $data
     *
     * @return null
     */
    public function put($data)
    {

        //
        // Does this file belong to this student?
        if ($this->getUser()->getId() != $this->getSubmission()->getOwner()->getId()) {
            throw new Sabre\DAV\Exception\Forbidden('Cannot overwrite another student\'s submission');
        }

        //
        // If the file was updated less than, say, 5 minutes ago, accept the
        // request - otherwise, reject it.
        $submit_time  = $this->getSubmission()->getCreatedAt()->getUnixTimestamp();
        $current_time = $_SERVER['REQUEST_TIME'];
        if (($current_time - $submit_time) > 300) {
            throw new Sabre\DAV\Exception\Forbidden('Cannot overwrite an existing file with the same name');
        }

        //
        // Load this file's contents.
        $storage = Storage\Mapper::getInstance()->createModel();
        $storage->writeStream($data);

        //
        // And put it in the submission object.
        $mapper = new Submission\FileMapper();
        $mapper->save($this->getSubmission()->setStorage($storage));
        return null;
    }
}
