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
	 * An instance of \Schoolbox\Assessment\Component\SubmissionBox\Submission
	 *
	 * @var null
	 */
	private $submission = null;

	/**
	 * An instance of \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork
	 *
	 * @var null
	 */
	private $returnedWork = null;

	/**
	 * Constructor
	 *
	 * @param \Schoolbox\Assessment\Component\SubmissionBox\Submission $submission
	 * @param \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork $returnedWork
	 */
	public function __construct(\Schoolbox\Assessment\Component\SubmissionBox\Submission $submission, \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork $returnedWork) {
		$this->setAuthenticator(new Authenticator());
		$this->setSubmission($submission);
		$this->setReturnedWork($returnedWork);
	}

	/**
	 * Download scribbled-on file and provide link to it
	 */
	public function start() {

		/* Crocodoc always downloads a pdf to you with annotations turned on
		 */
		$curl = new \Alaress_Curl_Url("https://crocodoc.com/api/v2/download/document?token={$this->getAuthenticator()->getApiToken()}&uuid={$this->getSubmission()->getUuid()}&annotated=true");
		$curl->exec();

		$data = $curl->getResult();

		/* If there were no errors, download/attach/save
		 * Otherwise, ignore altogether
		 */
		if (!count($curl->getErrors())) {

			/* Download to a temp dir
			 */
			$path = \Cfg::get('returns_dir') . '/' . $this->getReturnedWork()->getId() . '.dat';

			file_put_contents($path, $data);

			/* Update the return with some extra info
			 */
			$this->getReturnedWork()->setFilename('return_' . $this->getSubmission()->getFilename() . '.pdf');
			$this->getReturnedWork()->setFilesize(filesize($path));
			$this->getReturnedWork()->setMimeType('application/pdf');

			$returnMapper = \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper::getInstance();
			$returnMapper->save($this->getReturnedWork());
		}
	}

	/* Getters/Setters
	 */

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

	/**
	 * Set returned work
	 *
	 * @param null $returnedWork
	 */
	private function setReturnedWork($returnedWork) {
		$this->returnedWork = $returnedWork;
	}

	/**
	 * Get returned work
	 *
	 * @return null
	 */
	private function getReturnedWork() {
		return $this->returnedWork;
	}
}