<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\WebDav\SubjectServer;

use Sabre\DAV;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkResponse;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Resource\Storage;
use Schoolbox\User;

/**
 * A DAV collection representing a single submission within an assessment.
 */
class Response extends DAV\Collection
{
//    /**
//     * @var User
//     */
//    protected $user;

    /**
     * @var IResponse
     */
    private $response;

    /**
     * Constructor
     *
     * @param IResponse $response
     */
    public function __construct(IResponse $response)
    {
        $this->setResponse($response);
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
     * @param IResponse $response
     *
     * @return $this
     */
    private function setResponse($response)
    {
        $this->response = $response;
        return $this;
    }

    /**
     * @return IResponse
     */
    private function getResponse()
    {
        return $this->response;
    }

    /**
     * Gets the time which this file was uploaded.
     *
     * @return int
     */
    public function getLastModified()
    {
        return $this->getResponse()->getUpdatedAt()->getUnixTimeStamp();
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        $response = $this->getResponse();
        if ($response->isSubmitted()) {
            return sprintf('Draft (%s)', $response->getUpdatedAt()->getDatabase());
        }
        return sprintf('Submission (%s)', $response->getSubmittedAt()->getDatabase());
    }

    /**
     * Loads this submission's files.
     *
     * @return DAV\INode[]
     */
    public function getChildren()
    {
        $children = [];
//        $response = $this->getResponse();
//        if ($response instanceof DueWorkResponse) {
//            foreach ($response->getAllFiles() as $file) {
//                $children[] = new ResponseFile($file);
//            }
//        }
        return $children;
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
