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
	 * An instance of SubmissionReturn
	 *
	 * @var null
	 */
	private $returnHandler = null;

	/**
	 * Constructor
	 *
	 * @param $submissionId
	 * @param \SubmissionReturn $returnHandler
	 */
	public function __construct($submissionId, \SubmissionReturn $returnHandler) {
		$this->setAuthenticator(new Authenticator());
		$this->setSubmissionId($submissionId);
		$this->setReturnHandler($returnHandler);
	}

	/**
	 * Download scribbled-on file and provide link to it
	 */
	public function start() {
		$submissionMapper = new \Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper();
		$submission = $submissionMapper->findById($this->getSubmissionId());

		/* Find out if a return record exists
		 * "get the most recent return, or null if it doesn't exist."
		 */
		$returnCollection = $this->getReturnHandler()->getByUser($submission->getOwner()->getId(), $submission->getSubmissionBox()->getId(), 1);

		/* Update the latest return found for a submission
		 */
		if (count($returnCollection)) {
			$return = array_shift($returnCollection);

			$returnSaver = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkSaver();
			$returnSaver->saveReturn($return);

			/* This should either reuse the $latestReturn record, or spawn
			 * a new one instead
			 */
			$latestReturn = $returnSaver->getReturnedWork();

			/* Crocodoc always downloads a pdf to you with annotations turned on
			 */
			$curl = new \Alaress_Curl_Url("https://crocodoc.com/api/v2/download/document?token={$this->getAuthenticator()->getApiToken()}&uuid={$submission->getUuid()}&annotated=true");
			$curl->exec();

			$data = $curl->getResult();

			/* Download to a temp dir
			 */
			$path = \Cfg::get('returns_dir') . '/' . $latestReturn['id'] . '.dat';

			file_put_contents($path, $data);

			/* Update the return with some extra info
			 */
			$updatedReturn = array(
				'id' => $latestReturn['id'],
				'box_id' => $latestReturn['box_id'],
				'marker' => $latestReturn['marker'],
				'owner' => $latestReturn['owner'],
				'comment' => $latestReturn['comment'],
				'mark' => $latestReturn['mark'],
				'norm_mark' => $latestReturn['norm_mark'],
				'filename' => 'return_' . $submission->getFilename() . '.pdf',
				'filesize' => filesize($path),
				'mime_type' => 'application/pdf',
				'deleted' => $latestReturn['deleted'],
				'returned' => $latestReturn['returned'],
			);

			$this->getReturnHandler()->update($updatedReturn);
		}
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

	/**
	 * Set return handler
	 *
	 * @param null $returnHandler
	 */
	private function setReturnHandler($returnHandler) {
		$this->returnHandler = $returnHandler;
	}

	/**
	 * Get return handler
	 *
	 * @return null
	 */
	private function getReturnHandler() {
		return $this->returnHandler;
	}
}