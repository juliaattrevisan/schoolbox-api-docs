<?php

/**
 * Facade
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\InlineGrader;
use \Schoolbox\User;
use \Schoolbox\Assessment\Term\Model as Term;
use \Schoolbox\Assessment\Term\Mapper as TermMapper;
use \Schoolbox\Learning\Assessment\DueWork\Submission\Model as Submission;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as SubmissionFile;
use \Schoolbox\Learning\InlineGrader\Document;

class Facade
{

    /**
     * The file hash to upload
     *
     * @var SubmissionFile
     */
    private $file = '';

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
     * @param Submission $submission
     * @param SubmissionFile $file
     * @param User $user
     */
    public function __construct(Submission $submission, SubmissionFile $file, User $user)
    {
        $this->setFile($file);
        $this->setSubmission($submission);
        $this->setUser($user);

        /* Kinda need this, maybe?
         */
        $this->setTerm(TermMapper::getInstance()->findForDate($this->getSubmission()->getSubmittedAt()));
    }

    /**
     * Load a crocodoc into the iframe
     *
     * @throws \RangeException
     * @throws \DomainException
     */
    public function start()
    {
        /* If this was previously uploaded in the past, resume scribbling
         * Otherwise upload it and start a new scribble
         */
        if (!strlen($this->getFile()->getCrocodocId())) {
            $this->upload();
        }

        /* Sequential dependency - crocodoc requires a crocodoc id to
         * exist, otherwise it can't start, preventing us from initting
         * Loader() in constructor
         */
        return (new Loader(
            new Authenticator(),
            $this->getFile()->getCrocodocId(),
            $this->getUser(),
            $this->getTerm()->getArchived())
        )->annotate();
    }

    /**
     * Uploads the submission box to Crocodoc
     */
    protected function upload()
    {
        try {
            $document = new Document($this->getFile());
            $response = $document->upload();
        } catch (\RangeException $e) {
            echo "Error encountered: {$e->getMessage()}";
        }
    }

    /**
     * Redirects to a PDF download link for this object.
     */
    public function getDownloadUrl()
    {
        $filename = "return_{$this->getFile()->getFilename()}.pdf";

        /* Sequential dependency - crocodoc requires a crocodoc id to
         * exist, otherwise it can't start, preventing us from initting
         * Loader() in constructor
         */
        return (new Loader(
            new Authenticator(),
            $this->getFile()->getCrocodocId(),
            $this->getUser(),
            $this->getTerm()->getArchived())
        )->getDownloadUrl($filename);
    }

    /* Getters/Setters
     */

    /**
     * @return SubmissionFile
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @param SubmissionFile $file
     * @return $this
     */
    public function setFile($file)
    {
        $this->file = $file;
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