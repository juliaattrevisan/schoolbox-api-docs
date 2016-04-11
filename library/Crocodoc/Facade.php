<?php
/**
 * Facade
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Crocodoc;

use Exception;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\User;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\Assessment\Term;

class Facade
{

    /**
     * The submission id to upload
     *
     * @var string
     */
    private $submissionId = '';

    /**
     * The submission object itself
     *
     * @var Submission\File
     */
    private $submission;

    /**
     * The term which the submission object is in
     *
     * @var Term\Model
     */
    private $term;

    /**
     * @var User
     */
    private $user = null;

    /**
     * Constructor
     *
     * @param $submissionId
     * @param User $user
     */
    public function __construct($submissionId, User $user)
    {
        $this->setSubmissionId($submissionId);
        $this->loadSubmission($submissionId);
        $this->setUser($user);
    }

    /**
     * Loads a submission box from the supplied submission ID.
     *
     * @param $submissionId
     */
    protected function loadSubmission($submissionId)
    {
        $this->setSubmission(Submission\FileMapper::getInstance()->findById($submissionId));
        $this->setTerm(
            Term\Mapper::getInstance()->findForDate(
                SubmissionBoxMapper::getInstance()->findById(
                    $this->getSubmission()->getSubmissionBox()->getId()
                )->getDueDate()
            )
        );
    }

    /**
     * Uploads the submission box to Crocodoc
     */
    protected function upload()
    {
        try {
            $document = new Document($this->getSubmission());
            $response = $document->upload();
        } catch (\RangeException $e) {
            echo "Error encountered: ".$e->getMessage();
        }

        if ($response['error']) {
            echo json_encode($response);
        }
    }

	/**
	 * Start biting
	 *
	 * @throws Exception
	 */
	public function start() {

        /* If this was previously uploaded in the past, resume scribbling
         * Otherwise upload it and start a new scribble
         */
        $submission = $this->getSubmission();
        if (!strlen($submission->getUuid())) {
            $this->upload();
        }

        try {
            $loader = new Loader($submission->getUuid(), $this->getUser(), $this->getTerm()->getArchived());
            header('Location: '.$loader->render());
            exit;
        }
        catch (Exception $e) {
            echo "Error encountered: ".$e->getMessage();
        }
    }

    /**
     * Redirects to a PDF download link for this object.
     *
     * @throws Exception
     */
    public function download()
    {
        /* If this was previously uploaded in the past, resume scribbling
         * Otherwise upload it and start a new scribble
         */
        $submission = $this->getSubmission();
        if (!strlen($submission->getUuid())) {
            $this->upload();
        }

        try {
            $loader = new Loader($submission->getUuid(), $this->getUser(), $this->getTerm()->getArchived());
            header('Location: '.$loader->getDownloadUrl('return_'.$submission->getFilename().'.pdf'));
            exit;
        } catch (Exception $e) {
            echo "Error encountered: ".$e->getMessage();
        }
    }

    /* Getters/Setters
     */

    /**
     * Set submission id
     *
     * @param string $submissionId
     */
    private function setSubmissionId($submissionId)
    {
        $this->submissionId = $submissionId;
    }

    /**
     * Get submission id
     *
     * @return string
     */
    private function getSubmissionId()
    {
        return $this->submissionId;
    }

    /**
     * @return Submission\File
     */
    private function getSubmission()
    {
        return $this->submission;
    }

    /**
     * @param  Submission\File $submission
     *
     * @return $this
     */
    private function setSubmission($submission)
    {
        $this->submission = $submission;
        return $this;
    }

    /**
     * Set user
     *
     * @param User $user
     */
    private function setUser(User $user)
    {
        $this->user = $user;
    }

    /**
     * Get user
     *
     * @return User
     */
    private function getUser()
    {
        return $this->user;
    }

    /**
     * @return Term\Model
     */
    private function getTerm()
    {
        return $this->term;
    }

    /**
     * @param  Term\Model $term
     * @return $this
     */
    private function setTerm($term)
    {
        $this->term = $term;
        return $this;
    }
}
