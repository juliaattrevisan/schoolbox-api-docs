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
			echo $loader->render();
		}
		else {
			$document = new Document($submission);
			$response = $document->upload();

			/* Crocodoc-related errors, when a file dies in crocodoc
			 */
			if (isset($response['result']->error)) {

				/* To prevent vertical display of error message
				 */
				if (is_array($response['result']->error)) {
					echo json_encode(array(
						'errors' => $response['result']->error,
					));
				}
				else {
					echo json_encode(array(
						'errors' => array($response['result']->error),
					));
				}
			}
			/* If crocodoc is unreachable, or curl/network-related errors
			 */
			else if (count($response['errors'])) {
				echo json_encode(array(
					'errors' => $response['errors'],
				));
			}
			/* Otherwise everything went okay, thump out the url to the crocodoc
			 */
			else if (isset($response['result']->uuid)) {
				$loader = new Loader($response['result']->uuid, $this->getUser());
				echo $loader->render();

				/* Allow resuming of marking
				 */
				$submission->setUuid($response['result']->uuid);
				$submissionMapper->save($submission);
			}
		}
	}

	/* Getters/Setters
	 */

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

