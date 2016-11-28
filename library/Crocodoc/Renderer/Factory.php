<?php
/**
 * TemplateFactory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Crocodoc\Renderer;

use \Schoolbox\Assessment\Component\SubmissionBox\Submission;
use \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use \Schoolbox\Assessment\Term;
use \Schoolbox\Collection;

class Factory
{

	/**
	 * An instance of Submission
	 *
	 * @var Submission\Submission
	 */
	private $submission = null;

	/**
	 * All returned work for this submission box for this student.
	 *
	 * @var ReturnedWork[]|Collection
	 */
	private $returnedWorkCollection = null;

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
     * @var \SitePage
     */
    private $page = null;

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
	 * Force a submission object, or null
	 * (silly php)
	 *
	 * navigationElements is an array of previous, current and next students
	 * This is used in the Return Work control html, to determine where to go
	 * (some submissions don't use crocodoc)
	 *
	 * @param $page
	 * @param Submission\Submission $submission
	 * @param ReturnedWork[]|Collection $returnedWorkCollection
	 * @param $navigationElements
	 * @param Term\Model $term
	 */
	public function __construct(
		$page,
		Submission\Submission $submission = null,
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
     * Decide which template file to load based on the submission contents
     * Spawn a renderer
     */
    public function produce()
    {
        $work = $this->getSubmission();

        $returnCollection = $this->getReturnedWorkCollection();
        if ($work === null) {
            // No submission
            return new Strategy\None(
                $this->getPage(),
                $work,
                $returnCollection,
                $this->getNavigationElements(),
                $this->getTerm()
            );

        } elseif ($work instanceof Submission\Submission) {
            if ($this->crocodocable($work)) {
                // Crocodoc-friendly file types (only if Crocodoc is active with an api token)
                return new Strategy\Crocodoc(
                    $this->getPage(),
                    $work,
                    $returnCollection,
                    $this->getNavigationElements(),
                    $this->getTerm()
                );

            } elseif ($work->getMimeType()->isImage()) {
                // Other special presentation methods for inline viewing of media such as images and videos
                return new Strategy\Image(
                    $this->getPage(),
                    $work,
                    $returnCollection,
                    $this->getNavigationElements(),
                    $this->getTerm()
                );

            } elseif ($work->getMimetype()->isVideo()) {
                return new Strategy\Video(
                    $this->getPage(),
                    $work,
                    $returnCollection,
                    $this->getNavigationElements(),
                    $this->getTerm()
                );

            } elseif ($work->getMimetype()->isAudio()) {
                return new Strategy\Audio(
                    $this->getPage(),
                    $this->getSubmission(),
                    $returnCollection,
                    $this->getNavigationElements(),
                    $this->getTerm()
                );
            }
            elseif ($work->getMimeType()->matches('text/html')) {
                return new Strategy\Html(
                    $this->getPage(),
                    $work,
                    $returnCollection,
                    $this->getNavigationElements(),
                    $this->getTerm()
                );
            }
        }

        // Otherwise just download the file
        return new Strategy\Download(
            $this->getPage(),
            $work,
            $this->getReturnedWorkCollection(),
            $this->getNavigationElements(),
            $this->getTerm()
        );
    }

    /**
     * @param Submission\Submission $submission
     * @return bool
     */
    private function crocodocable(Submission\Submission $submission)
    {
        $ext = $submission->getStorage()->getExtension();

        return strlen(\Cfg::get('crocodoc_api_token')) && in_array($ext, $this->getAllowedFileTypes());
    }

	/* Getters/Setters
	 */

	/**
	 * Set submission
	 *
	 * @param Submission\Submission $submission
	 */
	private function setSubmission($submission) 
    {
		$this->submission = $submission;
	}

	/**
	 * Get submission
	 *
	 * @return Submission\Submission
	 */
	private function getSubmission() 
    {
		return $this->submission;
	}

    /**
     * Set allowed file types
     *
     * @param array $allowedFileTypes
     */
    private function setAllowedFileTypes($allowedFileTypes)
    {
        $this->allowedFileTypes = $allowedFileTypes;
    }

    /**
     * Get allowed file types
     *
     * @return array
     */
    private function getAllowedFileTypes()
    {
        return $this->allowedFileTypes;
    }

    /**
     * Set page
     *
     * @param SitePage $page
     */
    private function setPage($page)
    {
        $this->page = $page;
    }

    /**
     * Get page
     *
     * @return SitePage
     */
    private function getPage()
    {
        return $this->page;
    }

    /**
     * Set navigation elements
     *
     * @param array $navigationElements
     */
    private function setNavigationElements($navigationElements)
    {
        $this->navigationElements = $navigationElements;
    }

    /**
     * Get navigation elements
     *
     * @return array
     */
    private function getNavigationElements()
    {
        return $this->navigationElements;
    }

    /**
     * Set returned work collection
     *
     * @param ReturnedWork[]|Collection $returnedWork
     */
    private function setReturnedWorkCollection($returnedWork)
    {
        $this->returnedWorkCollection = $returnedWork;
    }

    /**
     * Get returned work collection
     *
     * @return ReturnedWork[]|Collection
     */
    private function getReturnedWorkCollection()
    {
        return $this->returnedWorkCollection;
    }

    /**
     * @return Term\Model
     */
    private function getTerm()
    {
        return $this->term;
    }

    /**
     * @param  Term\Model $term
     * @return $this
     */
    private function setTerm($term)
    {
        $this->term = $term;
        return $this;
    }
}