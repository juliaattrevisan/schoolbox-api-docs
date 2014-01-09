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
	 * The template file to inject
	 *
	 * @var string
	 */
	protected $templateFile = 'submission/inline/injectables/none.html';

	/**
	 * Constructor
	 *
	 * @param $page
	 * @param \Schoolbox\Assessment\Component\SubmissionBox\Submission $submission
	 */
	public function __construct($page, \Schoolbox\Assessment\Component\SubmissionBox\Submission $submission) {
		$this->setPage($page);
		$this->setSubmission($submission);
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
}