<?php
/**
 * Calendar_Event_Type_Submission
 *
 * Uses the Visitor pattern to render via an external class, finally decoupling
 * the data structure from rendering methodology in a highly granular environment
 * without needing to use singletons.
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Event_Type_Submission extends Calendar_Event_Type_Abstract {

	/* Renderer
	 */
	protected $rendererClass = 'Calendar_Event_Type_Submission_Renderer';

	/* Contextual: The date for submissions
	 */
	protected $date = null;

	/* Contextual: The owner of the submission box
 	 */
	protected $authorId = -1;
	protected $authorName = '';

	/* Category to which this submission box belongs
	 */
	protected $categoryName = '';

	/* TODO: Hardcoded?
	 */
	protected $eventType = 'Due Work';

	/* CSV string of class (category) codes
	 */
	private $categoryCodes = '';

	/* Submission box information
	 */
	private $submissionBoxId = -1;
	private $submissionBoxName = '';

	/**
	 * Render via external class
	 *
	 * @param \Calendar_Event_Submission_Renderer $visitor
	 */
	public function accept($visitor) {
		$visitor->visit($this);
	}

	/**
	 * Set submission note
	 */
	public function setSubmissionNote() {

		/* Optional categoryCodes suffix
		 */
		if (count($this->getCategoryCodes())) {
			$extended = ' (' . implode(', ',$this->getCategoryCodes()) . ')';
		}
		else {
			$extended = '';
		}

		$note = $this->getCategoryName() . $extended . '<br />' . $this->getSubmissionBoxName();

		$this->setNote($note);
	}

	/**
	 * Special time formattng
	 *
	 * @return string
	 */
	public function getDerivedTime() {
		$formatted = $this->getDate()->getUsingFormat('g:iA');

		if ($formatted != '11:59PM') {
			return $formatted;
		}

		return '';
	}

	/* Getters/Setters
	 */

	/**
	 * Set category codes
	 *
	 * @param $categoryCodes
	 */
	public function setCategoryCodes($categoryCodes) {
		$this->categoryCodes = explode(',',$categoryCodes);
	}

	/**
	 * Get category codes
	 *
	 * @return string
	 */
	public function getCategoryCodes() {
		return $this->categoryCodes;
	}

	/**
	 * Set submission box id
	 *
	 * @param $submissionBoxId
	 */
	public function setSubmissionBoxId($submissionBoxId) {
		$this->submissionBoxId = $submissionBoxId;
	}

	/**
	 * Get submission box id
	 *
	 * @return int
	 */
	public function getSubmissionBoxId() {
		return $this->submissionBoxId;
	}

	/**
	 * Set submission box name
	 *
	 * @param $submissionBoxName
	 */
	public function setSubmissionBoxName($submissionBoxName) {
		$this->submissionBoxName = $submissionBoxName;
	}

	/**
	 * Get submission box name
	 *
	 * @return string
	 */
	public function getSubmissionBoxName() {
		return $this->submissionBoxName;
	}
}
