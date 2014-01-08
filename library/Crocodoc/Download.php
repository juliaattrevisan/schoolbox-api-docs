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
	 * Submission id
	 *
	 * @var string
	 */
	private $submissionId = '';

	/**
	 * Constructor
	 *
	 * @param $submissionId
	 */
	public function __construct($submissionId) {
		$this->setAuthenticator(new Authenticator());
		$this->setSubmissionId($submissionId);
	}

	/**
	 * Download scribbled-on file and provide link to it
	 */
	public function start() {
		$submissionMapper = new \Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper();
		$submission = $submissionMapper->findById($this->getSubmissionId());

		$curl = new \Alaress_Curl_Url("https://crocodoc.com/api/v2/download/document?token={$this->getAuthenticator()->getApiToken()}&uuid={$submission->getUuid()}&annotated=true");

		$curl->exec();

		$data = $curl->getResult();

		/* Download to a temp dir
		 */
		$path = \Cfg::get('submission_upload_tmp_dir') . '/' . $submission->getId() . '.pdf';

		file_put_contents($path, $data);

		echo json_encode(array(
			'path' => $path
		));
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
	 * Set submission id
	 *
	 * @param string $submissionId
	 */
	private function setSubmissionId($submissionId) {
		$this->submissionId = $submissionId;
	}

	/**
	 * Get submission id
	 *
	 * @return string
	 */
	private function getSubmissionId() {
		return $this->submissionId;
	}
}