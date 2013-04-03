<?php
/**
 * Model for submission box, submission, return and assessment activities.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment;

use \Alaress_Date_Format;
use Schoolbox\User;
use Schoolbox\Resource\Category;
use Schoolbox\Assessment\Component\OnlineAssessment;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;

class Activity {

	/**
	 * @var int
	 */
	protected $id;

	/**
	 * @var string
	 */
	protected $event;

	/**
	 * @var Category
	 */
	protected $category;

	/**
	 * @var SubmissionBox
	 */
	protected $submission_box;

	/**
	 * @var OnlineAssessment
	 */
	protected $assessment;

	/**
	 * @var Submission
	 */
	protected $submission;

	/**
	 * @var ReturnedWork
	 */
	protected $returned_work;

	/**
	 * @var User
	 */
	protected $owner;

	/**
	 * @var Alaress_Date_Format
	 */
	protected $date;

//	public function __construct() {
//	}

	/**
	 * @param  \Alaress_Date_Format $date
	 *
	 * @return $this
	 */
	public function setDate($date) {
		$this->date = $date;
		return $this;
	}

	/**
	 * @return \Alaress_Date_Format
	 */
	public function getDate() {
		return $this->date;
	}

	/**
	 * @param  int $id
	 *
	 * @return $this
	 */
	public function setId($id) {
		$this->id = $id;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @param  \Schoolbox\User $owner
	 *
	 * @return $this
	 */
	public function setOwner($owner) {
		$this->owner = $owner;
		return $this;
	}

	/**
	 * @return \Schoolbox\User
	 */
	public function getOwner() {
		return $this->owner;
	}

	/**
	 * @param  \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork $returned_work
	 *
	 * @return $this
	 */
	public function setReturnedWork($returned_work) {
		$this->returned_work = $returned_work;
		return $this;
	}

	/**
	 * @return \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork
	 */
	public function getReturnedWork() {
		return $this->returned_work;
	}

	/**
	 * @param  \Schoolbox\Assessment\Component\SubmissionBox\Submission $submission
	 *
	 * @return $this
	 */
	public function setSubmission($submission) {
		$this->submission = $submission;
		return $this;
	}

	/**
	 * @return \Schoolbox\Assessment\Component\SubmissionBox\Submission
	 */
	public function getSubmission() {
		return $this->submission;
	}

	/**
	 * @param  \Schoolbox\Assessment\Component\SubmissionBox $submission_box
	 *
	 * @return $this
	 */
	public function setSubmissionBox($submission_box) {
		$this->submission_box = $submission_box;
		return $this;
	}

	/**
	 * @return \Schoolbox\Assessment\Component\SubmissionBox
	 */
	public function getSubmissionBox() {
		return $this->submission_box;
	}

	/**
	 * @param  string $event
	 *
	 * @return $this
	 */
	public function setEvent($event) {
		$this->event = $event;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getEvent() {
		return $this->event;
	}

	/**
	 * @param  \Schoolbox\Resource\Category $category
	 *
	 * @return $this
	 */
	public function setCategory($category) {
		$this->category = $category;
		return $this;
	}

	/**
	 * @return \Schoolbox\Resource\Category
	 */
	public function getCategory() {
		return $this->category;
	}

	/**
	 * @param  \Schoolbox\Assessment\Component\OnlineAssessment $assessment
	 *
	 * @return $this
	 */
	public function setAssessment($assessment) {
		$this->assessment = $assessment;
		return $this;
	}

	/**
	 * @return \Schoolbox\Assessment\Component\OnlineAssessment
	 */
	public function getAssessment() {
		return $this->assessment;
	}

}
