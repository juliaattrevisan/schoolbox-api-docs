<?php
/**
 * Model for teacher returns for student submissions into submission boxes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\User;

class ReturnedWork {

	/**
	 * @var int
	 */
	protected $id;

	/**
	 * @var SubmissionBox
	 */
	protected $submission_box;

	/**
	 * @var User
	 */
	protected $owner;

	/**
	 * @var string
	 */
	protected $comment;

	/**
	 * @var string
	 */
	protected $mark;

	/**
	 * @var float
	 */
	protected $normalized_mark;

	/**
	 * @var string
	 */
	protected $filename;

	/**
	 * @var \Alaress_Date_Format
	 */
	protected $submitted;

	/**
	 * @var \Alaress_Date_Format
	 */
	protected $returned;

	public function __construct() {
		$this->setSubmissionBox(new SubmissionBox());
		$this->setOwner(new User());
	}


	/**
	 * Calculates a gradient (from 0 to 10) from the normalized mark for this
	 * return.
	 *
	 * @return int
	 */
	public function getGradientFromNormalizedMark() {
		return max(0, min(10, (int)($this->getNormalizedMark() / 10)));
	}


	/**
	 * Auto-generated methods
	 */


	/**
	 * @param  string $filename
	 *
	 * @return $this
	 */
	public function setFilename($filename) {
		$this->filename = $filename;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getFilename() {
		return $this->filename;
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
	 * @param  \Alaress_Date_Format $submitted
	 *
	 * @return $this
	 */
	public function setSubmitted($submitted) {
		$this->submitted = $submitted;
		return $this;
	}

	/**
	 * @return \Alaress_Date_Format
	 */
	public function getSubmitted() {
		return $this->submitted;
	}

	/**
	 * @param  string $comment
	 *
	 * @return $this
	 */
	public function setComment($comment) {
		$this->comment = $comment;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getComment() {
		return $this->comment;
	}

	/**
	 * @param  string $mark
	 *
	 * @return $this
	 */
	public function setMark($mark) {
		$this->mark = $mark;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getMark() {
		return $this->mark;
	}

	/**
	 * @param  float $normalized_mark
	 *
	 * @return $this
	 */
	public function setNormalizedMark($normalized_mark) {
		$this->normalized_mark = $normalized_mark;
		return $this;
	}

	/**
	 * @return float
	 */
	public function getNormalizedMark() {
		return $this->normalized_mark;
	}

	/**
	 * @param  \Alaress_Date_Format $returned
	 *
	 * @return $this
	 */
	public function setReturned($returned) {
		$this->returned = $returned;
		return $this;
	}

	/**
	 * @return \Alaress_Date_Format
	 */
	public function getReturned() {
		return $this->returned;
	}

}
