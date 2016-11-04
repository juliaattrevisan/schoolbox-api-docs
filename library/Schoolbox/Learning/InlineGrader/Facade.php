<?php

/**
 * Facade
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use \Schoolbox\Learning\Assessment\DueWork\Submission\Model as Submission;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File as SubmissionFile;
use \Schoolbox\Assessment\Term\Model as Term;
use \Schoolbox\Assessment\Term\Mapper as TermMapper;

class Facade
{

    /**
     * The file hash to upload
     *
     * @var string
     */
    private $fileHash = '';

    /**
     * The submission object itself
     *
     * @var Submission
     */
    private $submission;

    /**
     * The term in which the Submission object was submitted
     *
     * @var Term
     */
    private $term;

    /**
     * @var User
     */
    private $user = null;

    /**
     * Facade constructor.
     *
     * @param $fileHash
     * @param Submission $submission
     * @param User $user
     */
    public function __construct($fileHash, Submission $submission, User $user)
    {
        $this->setFileHash($fileHash);
        $this->setSubmission($submission);
        $this->setUser($user);

        /* Kinda need this, maybe?
         */
        $this->setTerm(TermMapper::getInstance()->findForDate($this->getSubmission()->getSubmittedAt()));
    }

    /**
     * Load a crocodoc into the iframe
     */
    public function start()
    {
        /* If this was previously uploaded in the past, resume scribbling
         * Otherwise upload it and start a new scribble
         */
//        $submission = $this->getSubmission();
//        if (!strlen($submission->getUuid())) {
//            $this->upload();
//        }
//
//        try {
//            $loader = new Loader($submission->getUuid(), $this->getUser(), $this->getTerm()->getArchived());
//            header('Location: '.$loader->render());
//            exit;
//        }
//        catch (Exception $e) {
//            echo "Error encountered: ".$e->getMessage();
//        }
    }

//    /**
//     * Uploads the submission box to Crocodoc
//     */
//    protected function upload()
//    {
//        $response = [];
//
//        try {
//            $document = new Document($this->getSubmission());
//            $response = $document->upload();
//        } catch (\RangeException $e) {
//            echo "Error encountered: {$e->getMessage()}";
//        }
//
//        if ($response['error']) {
//            echo json_encode($response);
//        }
//    }
//
//    /**
//     * Start biting
//     *
//     * @throws Exception
//     */
//    public function start() {
//

//    }
//
//    /**
//     * Redirects to a PDF download link for this object.
//     *
//     * @throws Exception
//     */
//    public function download()
//    {
//        /* If this was previously uploaded in the past, resume scribbling
//         * Otherwise upload it and start a new scribble
//         */
//        $submission = $this->getSubmission();
//        if (!strlen($submission->getUuid())) {
//            $this->upload();
//        }
//
//        try {
//            $loader = new Loader($submission->getUuid(), $this->getUser(), $this->getTerm()->getArchived());
//            header('Location: '.$loader->getDownloadUrl('return_'.$submission->getFilename().'.pdf'));
//            exit;
//        } catch (Exception $e) {
//            echo "Error encountered: ".$e->getMessage();
//        }
//    }

    /* Getters/Setters
     */

    /**
     * @return string
     */
    private function getFileHash()
    {
        return $this->fileHash;
    }

    /**
     * @param string $fileHash
     * @return $this
     */
    private function setFileHash($fileHash)
    {
        $this->fileHash = $fileHash;
        return $this;
    }

    /**
     * @return Submission
     */
    private function getSubmission()
    {
        return $this->submission;
    }

    /**
     * @param Submission $submission
     * @return $this
     */
    private function setSubmission($submission)
    {
        $this->submission = $submission;
        return $this;
    }

    /**
     * @return Term
     */
    private function getTerm()
    {
        return $this->term;
    }

    /**
     * @param Term $term
     * @return $this
     */
    private function setTerm($term)
    {
        $this->term = $term;
        return $this;
    }

    /**
     * @return User
     */
    private function getUser()
    {
        return $this->user;
    }

    /**
     * @param User $user
     * @return $this
     */
    private function setUser($user)
    {
        $this->user = $user;
        return $this;
    }
}