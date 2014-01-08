<?php
/**
 * Download
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Crocodoc;


class Download {

	/**
	 * Contains API keys
	 *
	 * @var null
	 */
	private $authenticator = null;

	/**
	 * The uuid of the crocodoc document
	 *
	 * @var string
	 */
	private $uuid = '';

	/**
	 * An instance of \Schoolbox\Assessment\Component\SubmissionBox\Submission
	 *
	 * @var null
	 */
	private $submission = null;

	/**
	 * Constructor
	 *
	 * @param \Schoolbox\Assessment\Component\SubmissionBox\Submission $submission
	 */
	public function __construct(\Schoolbox\Assessment\Component\SubmissionBox\Submission $submission) {
		$this->setAuthenticator(new Authenticator());
		$this->setSubmission($submission);
	}

	/**
	 * Download scribbled-on file and provide link to it
	 */
	public function start() {

		/* Update the latest return found for a submission
		 * Either recycle an existing record or spawn a new one
		 */
		$return = array(
			'box_id' => $this->getSubmission()->getSubmissionBox()->getId(),
			'marker' => $_SESSION['user']->getId(),
			'owner' => $this->getSubmission()->getOwner()->getId(),
		);

		/* Do what returnWorkForm usually does
		 */
		$returnSaver = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkSaver();
		$returnSaver->saveReturn($return);

		/* This should either reuse the $latestReturn record, or spawn
		 * a new one instead
		 */
		$latestReturn = $returnSaver->getReturnedWork();

		/* Crocodoc always downloads a pdf to you with annotations turned on
		 */
		$curl = new \Alaress_Curl_Url("https://crocodoc.com/api/v2/download/document?token={$this->getAuthenticator()->getApiToken()}&uuid={$this->getSubmission()->getUuid()}&annotated=true");
		$curl->exec();

		$data = $curl->getResult();

		/* Download to a temp dir
		 */
		$path = \Cfg::get('returns_dir') . '/' . $latestReturn->getId() . '.dat';

		file_put_contents($path, $data);

		/* Update the return with some extra info
		 */
		$latestReturn->setFilename('return_' . $this->getSubmission()->getFilename() . '.pdf');
		$latestReturn->setFilesize(filesize($path));
		$latestReturn->setMimeType('application/pdf');

		$returnMapper = \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper::getInstance();
		$returnMapper->save($latestReturn);
	}

	/* Getters/Setters
	 */

	/**
	 * Set uuid
	 *
	 * @param string $uuid
	 */
	private function setUuid($uuid) {
		$this->uuid = $uuid;
	}

	/**
	 * Get uuid
	 *
	 * @return string
	 */
	private function getUuid() {
		return $this->uuid;
	}

	/**
	 * Set authenticator
	 *
	 * @param null $authenticator
	 */
	private function setAuthenticator($authenticator) {
		$this->authenticator = $authenticator;
	}

	/**
	 * Get authenticator
	 *
	 * @return null
	 */
	private function getAuthenticator() {
		return $this->authenticator;
	}

	/**
	 * Set submission
	 *
	 * @param null $submission
	 */
	private function setSubmission($submission) {
		$this->submission = $submission;
	}

	/**
	 * Get submission
	 *
	 * @return null
	 */
	private function getSubmission() {
		return $this->submission;
	}
}