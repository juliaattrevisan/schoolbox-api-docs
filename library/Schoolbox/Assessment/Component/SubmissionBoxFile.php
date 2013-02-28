<?php
/**
 * Model for files that a teacher attaches as part of a submission box.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component;
use Schoolbox;

class SubmissionBoxFile {

	/**
	 * @var int
	 */
	protected $id;

	/**
	 * @var int
	 */
	protected $storage_id;

	/**
	 * @var int
	 */
	protected $submission_box_id;

	/**
	 * @param int $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @param int $storage_id
	 */
	public function setStorageId($storage_id) {
		$this->storage_id = $storage_id;
	}

	/**
	 * @return int
	 */
	public function getStorageId() {
		return $this->storage_id;
	}

	/**
	 * @param int $submission_box_id
	 */
	public function setSubmissionBoxId($submission_box_id) {
		$this->submission_box_id = $submission_box_id;
	}

	/**
	 * @return int
	 */
	public function getSubmissionBoxId() {
		return $this->submission_box_id;
	}

}
