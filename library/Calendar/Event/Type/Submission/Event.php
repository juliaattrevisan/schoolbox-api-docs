<?php
/**
 * Calendar_Event_Type_Submission
 *
 * Uses the Visitor pattern to render via an external class, finally decoupling
 * the data structure from rendering methodology in a highly granular environment
 * without needing to use singletons.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Calendar\Event\Type\Submission;
use Calendar\Event\Type\EventBase;
use Calendar\Event\Type\EventInterface;


class Event extends EventBase implements EventInterface {

    /* Renderer
     */
    protected $rendererClass = 'Calendar_Event_Type_Submission_Renderer_Javascript';


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

    /**
     * Is this an online assessment or not?
     *
     * @todo Debate whether this should be shoehorned into a submission event
     *
     * @var bool
     */
    protected $is_assessment = false;

    /* CSV string of class (category) codes
     */
    private $categoryCodes = array();

    /* Submission box information
     */
    private $submissionBoxId = -1;
    private $submissionBoxName = '';
    private $submissionBoxDetail = '';

	/**
	 * Render via external class
	 *
	 * @param \Calendar_Event_StudentBirthday_Renderer $visitor
	 *
	 * @return mixed
	 */
    public function accept($visitor) {
        return $visitor->visit($this);
    }

    /**
     * Get submission note
	 *
	 * @return string
	 */
    public function getTitle() {

        /* Optional categoryCodes suffix
         */
        if (count($this->getCategoryCodes())) {
            $extended = ' (' . $this->getCategoryCodeString() . ')';
        } else {
            $extended = '';
        }

        return $this->getCategoryName() . $extended . "\n" . $this->getSubmissionBoxName();
    }

	/**
	 * Event visibility; generates a CSS string
	 *
	 * @return string
	 */
	public function getDerivedEventLevel() {
		return 'personal';
	}

    /* Getters/Setters
     */

    /**
     * Set category codes
     *
     * @param $categoryCodes
     */
    public function setCategoryCodes($categoryCodes) {
        $this->categoryCodes = $categoryCodes;
    }

    /**
     * Get category codes
     *
     * @return array
     */
    public function getCategoryCodes() {
        return $this->categoryCodes;
    }

    /**
     * Get category string
     * @return string
     */
    public function getCategoryCodeString() {
        return implode(', ', $this->getCategoryCodes());
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

    /**
     * Set submission box detail
     *
     * @param $submissionBoxName
     */
    public function setSubmissionBoxDetail($submissionBoxDetail) {
        $this->submissionBoxDetail = $submissionBoxDetail;
    }

    /**
     * Get submission box name
     *
     * @return string
     */
    public function getSubmissionBoxDetail() {
        return $this->submissionBoxDetail;
    }

    /**
     * @param  bool $is_assessment
     *
     * @return $this
     */
    public function setIsAssessment($is_assessment) {
        $this->is_assessment = $is_assessment;
        return $this;
    }

    /**
     * @return bool
     */
    public function getIsAssessment() {
        return $this->is_assessment;
    }
}
