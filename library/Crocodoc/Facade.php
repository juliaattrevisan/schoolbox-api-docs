<?php
/**
 * Facade
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Crocodoc;


class Facade {

	/**
	 * The submission id to upload
	 *
	 * @var string
	 */
	private $submissionId = '';

	/**
	 * An instance of Schoobox\User
	 *
	 * @var null
	 */
	private $user = null;

	/**
	 * Constructor
	 *
	 * @param $submissionId
	 * @param \Schoolbox\User $user
	 */
	public function __construct($submissionId, \Schoolbox\User $user) {
		$this->setSubmissionId($submissionId);
		$this->setUser($user);
	}

	/**
	 * Start biting
	 *
	 * @throws Exception
	 */
	public function start() {
		$submissionMapper = new \Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper();
		$submission = $submissionMapper->findById($this->getSubmissionId());

		/* If this was previously uploaded in the past, resume scribbling
		 * Otherwise upload it and start a new scribble
		 */
		if (strlen($submission->getUuid())) {
			$loader = new Loader($submission->getUuid(), $this->getUser());
			$loader->render();
		}
		else {
			$document = new Document($submission);
			$result = $document->upload();

			if (isset($result->error)) {
				throw new \Exception($result->error);
			}

			if (isset($result->uuid)) {
				$loader = new Loader($result->uuid, $this->getUser());
				$loader->render();

				/* Allow resuming of marking
				 */
				$submission->setUuid($result->uuid);
				$submissionMapper->save($submission);
			}
		}
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
	 * Set user
	 *
	 * @param null $user
	 */
	private function setUser($user) {
		$this->user = $user;
	}

	/**
	 * Get user
	 *
	 * @return null
	 */
	private function getUser() {
		return $this->user;
	}
}

