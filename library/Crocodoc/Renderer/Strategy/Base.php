<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Crocodoc\Renderer\Strategy;

use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Assessment\Term;
use Schoolbox\Collection;
use SitePage;

abstract class Base {

	/**
	 * An instance of sitepage
	 *
	 * @var SitePage
	 */
	protected $page = null;

	/**
	 * The submission
	 *
	 * @var Submission\File
	 */
	protected $submission = null;

	/**
	 * Returned work collection
	 *
	 * @var ReturnedWork[]|Collection
	 */
	protected $returnedWorkCollection = null;

	/**
	 * An array of previous, current and next students
	 *
	 * @var array
	 */
	private $navigationElements = array();

	/**
	 * The term that this submission box is in (or null, if I don't care)
	 *
	 * @var Term\Model
	 */
	private $term;

	/**
	 * The template file to inject
	 *
	 * @var string
	 */
	protected $templateFile = 'submission/inlineGrading/injectables/views/none.html';

	/**
	 * Control file, to determine how to return work
	 *
	 * @var string
	 */
	protected $controlFile = 'submission/inlineGrading/injectables/controls/redirect-return-control.html';

	/**
	 * Upload file
	 *
	 * @var string
	 */
	protected $uploadFile = 'submission/inlineGrading/injectables/controls/upload-file.html';

	/**
	 * Constructor
	 *
	 * @param $page
	 * @param Submission\File $submission
	 * @param ReturnedWork[]|Collection $returnedWorkCollection
	 * @param $navigationElements
	 * @param Term\Model $term
	 */
	public function __construct(
		$page,
		Submission\File $submission = null,
		$returnedWorkCollection = null,
		$navigationElements = [],
		Term\Model $term = null
	) {
		$this->setPage($page);
		$this->setSubmission($submission);
		$this->setReturnedWorkCollection($returnedWorkCollection);
		$this->setNavigationElements($navigationElements);
		$this->setTerm($term instanceof Term\Model ? $term : new Term\Model());
	}

	/**
	 * Render injectable
	 *
	 * @return mixed
	 */
	abstract public function render();

	/**
	 * Parse the Upload File injectable
	 */
	protected function parseInjectableUpload()
    {
		$this->getPage()->setFile('injectableUpload', $this->getUploadFile());
		$this->getPage()->setBlock('injectableUpload', 'returned_work', 'returned_work_block');
        $this->getPage()->setVar('returned_work_block', '');

		if ($this->getReturnedWorkCollection() != null) {
			foreach ($this->getReturnedWorkCollection() as $return) {
				if ($return->getStorage()->getFilename() != null) {
					$storage = $return->getStorage();

					$this->getPage()->setVar(array(
						'MOST_RECENT_RETURN_FILE_ID' => $return->getId(),
						'MOST_RECENT_RETURN_FILE_NAME' => $storage->getBasename(),
						'MOST_RECENT_RETURN_FILE_SIZE' => \Alaress_Byte_Format::bytesToString($storage->getSize()),
						'MOST_RECENT_RETURN_FILE_THUMB' => $storage->getMimeType()->getIconFile(),
						'MOST_RECENT_RETURN_FILE_DATE' => $return->getCreatedAt()->getRelativeTime(),
					));

					$this->getPage()->parse('returned_work_block', 'returned_work', true);
				}
			}
		}

		$this->getPage()->parse('INJECTED_MARK_TEMPLATE', 'injectableUpload');
	}

	/**
	 * Determine what the next person's url is
	 *
	 * @return string
	 */
	protected function getNextUrl() {
		$navigationElements = $this->getNavigationElements();

		if (is_array($navigationElements['next'])) {
			$nextUrl = $navigationElements['next']['navigation']['value'];
		}
		else {
			$nextUrl = '/submission/viewSubmission/' . $navigationElements['boxId'];
		}

		return $nextUrl;
	}

	/* Getters/Setters
	 */

	/**
	 * Set page
	 *
	 * @param SitePage $page
	 */
	protected function setPage(SitePage $page) {
		$this->page = $page;
	}

	/**
	 * Get page
	 *
	 * @return SitePage
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
	 * @param Submission\File $submission
	 */
	protected function setSubmission($submission) {
		$this->submission = $submission;
	}

	/**
	 * Get submission
	 *
	 * @return Submission\File
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

	/**
	 * Get upload file
	 *
	 * @param string $uploadFile
	 */
	protected function setUploadFile($uploadFile) {
		$this->uploadFile = $uploadFile;
	}

	/**
	 * Set upload file
	 *
	 * @return string
	 */
	protected function getUploadFile() {
		return $this->uploadFile;
	}

	/**
	 * Set returned work
	 *
	 * @param ReturnedWork[]|Collection $returnedWorkCollection
	 */
	protected function setReturnedWorkCollection($returnedWorkCollection) {
		$this->returnedWorkCollection = $returnedWorkCollection;
	}

	/**
	 * Get returned work
	 *
	 * @return ReturnedWork[]|Collection
	 */
	protected function getReturnedWorkCollection() {
		return $this->returnedWorkCollection;
	}

	/**
	 * @return Term\Model
	 */
	protected function getTerm()
	{
		return $this->term;
	}

	/**
	 * @param  Term\Model $term
	 * @return $this
	 */
	protected function setTerm($term)
	{
		$this->term = $term;
		return $this;
	}
}
