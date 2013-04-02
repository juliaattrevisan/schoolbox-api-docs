<?php
/**
 * Model for student submissions into submission boxes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\User;

class Submission {

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
	protected $filename;

	/**
	 * @var \Alaress_Date_Format
	 */
	protected $submitted;

	/**
	 * @var bool
	 */
	protected $marked;

	/**
	 * @var bool
	 */
	protected $late;

	/**
	 * @var string
	 */
	protected $mimetype;


	public function __construct() {
		$this->setSubmissionBox(new SubmissionBox());
		$this->setOwner(new User());
	}


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
	 * @param  boolean $late
	 *
	 * @return $this
	 */
	public function setLate($late) {
		$this->late = $late;
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getLate() {
		return $this->late;
	}

	/**
	 * @param  boolean $marked
	 *
	 * @return $this
	 */
	public function setMarked($marked) {
		$this->marked = $marked;
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getMarked() {
		return $this->marked;
	}

	/**
	 * @param  string $mimetype
	 *
	 * @return $this
	 */
	public function setMimetype($mimetype) {
		$this->mimetype = $mimetype;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getMimetype() {
		return $this->mimetype;
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

}
