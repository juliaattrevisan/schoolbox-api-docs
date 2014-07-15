<?php
/**
 * TemplateFactory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Crocodoc\Renderer;

use \Schoolbox\Assessment\Component\SubmissionBox\Submission;
use \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;

class Factory {

	/**
	 * An instance of Submission
	 *
	 * @var null
	 */
	private $submission = null;

	/**
	 * An instance of ReturnedWork
	 *
	 * @var null
	 */
	private $returnedWork = null;

	/**
	 * Supported mime types
	 *
	 * @var array
	 */
	private $allowedFileTypes = array(
		'doc', 'docx',
		'xls', 'xlsx',
		'ppt', 'pptx',
		'pdf'
	);

	/**
	 * An instance of SitePage
	 *
	 * @var null
	 */
	private $page = null;

	/**
	 * An array of previous, current and next students
	 *
	 * @var array
	 */
	private $navigationElements = array();

	/**
	 * Force a submission object, or null
	 * (silly php)
	 *
	 * navigationElements is an array of previous, current and next students
	 * This is used in the Return Work control html, to determine where to go
	 * (some submissions don't use crocodoc)
	 *
	 * @param $page
	 * @param Submission $submission
	 * @param ReturnedWork $returnedWork
	 * @param $navigationElements
	 */
	public function __construct($page, Submission $submission = null, ReturnedWork $returnedWork = null, $navigationElements) {
		$this->setPage($page);
		$this->setSubmission($submission);
		$this->setReturnedWork($returnedWork);
		$this->setNavigationElements($navigationElements);
	}

	/**
	 * Decide which template file to load based on the submission contents
	 * Spawn a renderer
	 */
	public function produce() {

		/* No submission
		 */
		if ($this->getSubmission() === null) {
			return new Strategy\None($this->getPage(), $this->getSubmission(), $this->getReturnedWork(), $this->getNavigationElements());
		}
		/* Crocodoc-friendly file types (only if Crocodoc is active with an api token)
		 */
		else if (strlen(\Cfg::get('crocodoc_api_token')) && in_array($this->getSubmission()->getFileExtension(), $this->getAllowedFileTypes())) {
			return new Strategy\Crocodoc($this->getPage(), $this->getSubmission(), $this->getReturnedWork(), $this->getNavigationElements());
		}
		/* Other special presentation methods for inline viewing of media
		 * such as images and videos
		 */
		else if (preg_match('#^image/*#',($this->getSubmission()->getMimeType()->getMime()))) {
			return new Strategy\Image($this->getPage(), $this->getSubmission(), $this->getReturnedWork(), $this->getNavigationElements());
		}
		else if (preg_match('#^video/*#',($this->getSubmission()->getMimeType()->getMime()))) {
			return new Strategy\Video($this->getPage(), $this->getSubmission(), $this->getReturnedWork(), $this->getNavigationElements());
		}
		else if (preg_match('#^text/html#',($this->getSubmission()->getMimeType()->getMime()))) {
			return new Strategy\Html($this->getPage(), $this->getSubmission(), $this->getReturnedWork(), $this->getNavigationElements());
		}
		/* Otherwise just download the file
		 */
		else {
			return new Strategy\Download($this->getPage(), $this->getSubmission(), $this->getReturnedWork(), $this->getNavigationElements());
		}

		return new Strategy\None($this->getPage(), $this->getSubmission(), $this->getReturnedWork(), $this->getNavigationElements());
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

	/**
	 * Set navigation elements
	 *
	 * @param array $navigationElements
	 */
	private function setNavigationElements($navigationElements) {
		$this->navigationElements = $navigationElements;
	}

	/**
	 * Get navigation elements
	 *
	 * @return array
	 */
	private function getNavigationElements() {
		return $this->navigationElements;
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