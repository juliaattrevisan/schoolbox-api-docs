<?php
/**
 * TemplateFactory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Crocodoc;


class TemplateFactory {

	/**
	 * An instance of Submission
	 *
	 * @var null
	 */
	private $submission = null;

	/**
	 * Supported mime types
	 *
	 * @var array
	 */
	private $allowedFileTypes = array(
		'doc', 'docx',
		'xls', 'xlsx',
		'ppt', 'pptx'
	);

	/**
	 * Force a submission object, or null
	 * (silly php)
	 *
	 * @param \Schoolbox\Assessment\Component\SubmissionBox\Submission $submission
	 */
	public function __construct(\Schoolbox\Assessment\Component\SubmissionBox\Submission $submission = null) {
		$this->setSubmission($submission);
	}

	/**
	 * Decide which template file to load based on the submission contents
	 */
	public function produce() {
		if ($this->getSubmission() === null) {
			return 'submission/inline/none.html';
		}
		else if (in_array($this->getSubmission()->getFileExtension(), $this->getAllowedFileTypes())) {
			return 'submission/inline/view.html';
		}
		else {
			return 'submission/inline/download.html';
		}

		return 'submission/inline/none.html';
	}

	/* Getters/Setters
	 */

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
	 * Set allowed file types
	 *
	 * @param array $allowedFileTypes
	 */
	private function setAllowedFileTypes($allowedFileTypes) {
		$this->allowedFileTypes = $allowedFileTypes;
	}

	/**
	 * Get allowed file types
	 *
	 * @return array
	 */
	private function getAllowedFileTypes() {
		return $this->allowedFileTypes;
	}
}