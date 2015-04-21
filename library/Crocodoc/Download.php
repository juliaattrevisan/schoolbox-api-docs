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

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\Resource\Storage;

class Download {

	/**
	 * Contains API keys
	 *
	 * @var Authenticator
	 */
	private $authenticator = null;

	/**
	 * An instance of SubmissionBox\Submission
	 *
	 * @var Submission\File
	 */
	private $submission = null;

	/**
	 * An instance of SubmissionBox\ReturnedWork
	 *
	 * @var SubmissionBox\ReturnedWork
	 */
	private $returnedWork = null;

	/**
	 * Constructor
	 *
	 * @param Submission\File $submission
	 * @param SubmissionBox\ReturnedWork $returnedWork
	 */
	public function __construct(Submission\File $submission, SubmissionBox\ReturnedWork $returnedWork) {
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
        if (count($curl->getErrors())) {
            return;
        }

        $temp = tempnam(sys_get_temp_dir(), 'cd');
        file_put_contents($temp, $data);

        $filename = 'return_' . $this->getSubmission()->getFilename() . '.pdf';
        $storage  = Storage\Mapper::getInstance()->accept($filename, $temp, false);

        $this->getReturnedWork()->setStorage($storage);

        $returnMapper = SubmissionBox\ReturnedWorkMapper::getInstance();
        $returnMapper->save($this->getReturnedWork());
	}

	/* Getters/Setters
	 */

	/**
	 * Set authenticator
	 *
	 * @param Authenticator $authenticator
	 */
	private function setAuthenticator($authenticator) {
		$this->authenticator = $authenticator;
	}

	/**
	 * Get authenticator
	 *
	 * @return Authenticator
	 */
	private function getAuthenticator() {
		return $this->authenticator;
	}

	/**
	 * Set submission
	 *
	 * @param Submission\File $submission
	 */
	private function setSubmission($submission) {
		$this->submission = $submission;
	}

	/**
	 * Get submission
	 *
	 * @return Submission\File
	 */
	private function getSubmission() {
		return $this->submission;
	}

	/**
	 * Set returned work
	 *
	 * @param SubmissionBox\ReturnedWork $returnedWork
	 */
	private function setReturnedWork($returnedWork) {
		$this->returnedWork = $returnedWork;
	}

	/**
	 * Get returned work
	 *
	 * @return SubmissionBox\ReturnedWork
	 */
	private function getReturnedWork() {
		return $this->returnedWork;
	}
}