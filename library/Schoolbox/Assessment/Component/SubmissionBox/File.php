<?php
/**
 * Model for files that a teacher attaches as part of a submission box.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox;
use Schoolbox\Resource;

class File {

	/**
	 * @var int
	 */
	protected $id;

	/**
	 * @var int
	 */
	protected $file_id;

	/**
	 * @var int
	 */
	protected $submission_box_id;

	/**
	 * @var Resource\File
	 */
	protected $file;

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
	 * @param int $file_id
	 */
	public function setFileId($file_id) {
		$this->file_id = $file_id;
	}

	/**
	 * @return int
	 */
	public function getFileId() {
		return $this->file_id;
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

	/**
	 * @param Resource\File $file
	 */
	public function setFile(Resource\File $file) {
		$this->file = $file;
	}

	/**
	 * @return Resource\File
	 */
	public function getFile() {
		return $this->file;
	}
}
