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
use Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper;
use Schoolbox\User;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;

class Facade
{

    /**
     * The submission id to upload
     *
     * @var string
     */
    private $submissionId = '';

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
        $this->setUser($user);
    }

	/**
	 * Start biting
	 *
	 * @throws Exception
	 */
	public function start() {
		$submissionMapper = new Submission\FileMapper();

        /** @var Submission\File $submission */
		$submission = $submissionMapper->findById($this->getSubmissionId());

        /* If this was previously uploaded in the past, resume scribbling
         * Otherwise upload it and start a new scribble
         */
        if (!strlen($submission->getUuid())) {
            $document = new Document($submission);
            $response = $document->upload();

            if ($response['error']) {
                echo json_encode($response);
            }
        }

        try {
            $loader = new Loader($submission->getUuid(), $this->getUser());
            header('Location: '.$loader->render());
            exit;
        }
        catch (Expception $e) {
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
}

