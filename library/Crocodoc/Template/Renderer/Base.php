<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Crocodoc\Template\Renderer;


abstract class Base {

	/**
	 * An instance of sitepage
	 *
	 * @var null
	 */
	protected $page = null;

	/**
	 * The submission
	 *
	 * @var null
	 */
	protected $submission = null;

	/**
	 * An array of previous, current and next students
	 *
	 * @var array
	 */
	private $navigationElements = array();

	/**
	 * The template file to inject
	 *
	 * @var string
	 */
	protected $templateFile = 'submission/inline/injectables/none.html';

	/**
	 * Control file, to determine how to return work
	 *
	 * @var string
	 */
	protected $controlFile = 'submission/inline/injectables/redirect-return-control.html';

	/**
	 * Constructor
	 *
	 * @param $page
	 * @param \Schoolbox\Assessment\Component\SubmissionBox\Submission $submission
	 * @param $navigationElements
	 */
	public function __construct($page, \Schoolbox\Assessment\Component\SubmissionBox\Submission $submission = null, $navigationElements) {
		$this->setPage($page);
		$this->setSubmission($submission);
		$this->setNavigationElements($navigationElements);
	}

	/**
	 * Render injectable
	 *
	 * @return mixed
	 */
	abstract public function render();

	/* Getters/Setters
	 */

	/**
	 * Set page
	 *
	 * @param null $page
	 */
	protected function setPage($page) {
		$this->page = $page;
	}

	/**
	 * Get page
	 *
	 * @return null
	 */
	protected function getPage() {
		return $this->page;
	}

	/**
	 * Set template file
	 *
	 * @param string $templateFile
	 */
	protected function setTemplateFile($templateFile) {
		$this->templateFile = $templateFile;
	}

	/**
	 * Get template file
	 *
	 * @return string
	 */
	protected function getTemplateFile() {
		return $this->templateFile;
	}

	/**
	 * Set submission
	 *
	 * @param null $submission
	 */
	protected function setSubmission($submission) {
		$this->submission = $submission;
	}

	/**
	 * Get submission
	 *
	 * @return null
	 */
	protected function getSubmission() {
		return $this->submission;
	}

	/**
	 * Set control file
	 *
	 * @param string $controlFile
	 */
	protected function setControlFile($controlFile) {
		$this->controlFile = $controlFile;
	}

	/**
	 * Get control file
	 *
	 * @return string
	 */
	public function getControlFile() {
		return $this->controlFile;
	}

	/**
	 * Set navigation elements
	 *
	 * @param array $navigationElements
	 */
	protected function setNavigationElements($navigationElements) {
		$this->navigationElements = $navigationElements;
	}

	/**
	 * Get navigation elements
	 *
	 * @return array
	 */
	protected function getNavigationElements() {
		return $this->navigationElements;
	}
}