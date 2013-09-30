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
	 * @var Schoolbox\Storage
	 */
	protected $file;

	public function __construct() {
		$this->setFile(new Schoolbox\Storage());
	}

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
	 * @param Schoolbox\Storage $file
	 */
	public function setFile(Schoolbox\Storage $file) {
		$this->file = $file;
	}

	/**
	 * @return Schoolbox\Storage
	 */
	public function getFile() {
		return $this->file;
	}

	/**
	 * Does this submission box file entry have an actual file attached?
	 *
	 * @return bool
	 */
	public function hasFile() {
		return strlen($this->file->getFilename()) > 0;
	}
}
