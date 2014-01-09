<?php
/**
 * TemplateFactory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Crocodoc\Template;


class Factory {

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
	 * An instance of SitePage
	 *
	 * @var null
	 */
	private $page = null;

	/**
	 * Force a submission object, or null
	 * (silly php)
	 *
	 * @param $page
	 * @param \Schoolbox\Assessment\Component\SubmissionBox\Submission $submission
	 */
	public function __construct($page, \Schoolbox\Assessment\Component\SubmissionBox\Submission $submission = null) {
		$this->setPage($page);
		$this->setSubmission($submission);
	}

	/**
	 * Decide which template file to load based on the submission contents
	 * Spawn a renderer
	 */
	public function produce() {
		if ($this->getSubmission() === null) {
			return new Renderer\None($this->getPage(), $this->getSubmission());
		}
		else if (in_array($this->getSubmission()->getFileExtension(), $this->getAllowedFileTypes())) {
			return new Renderer\Crocodoc($this->getPage(), $this->getSubmission());
		}
		else {
			return new Renderer\Download($this->getPage(), $this->getSubmission());
		}

		return new Renderer\None($this->getPage(), $this->getSubmission());
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

	/**
	 * Set page
	 *
	 * @param null $page
	 */
	private function setPage($page) {
		$this->page = $page;
	}

	/**
	 * Get page
	 *
	 * @return null
	 */
	private function getPage() {
		return $this->page;
	}
}