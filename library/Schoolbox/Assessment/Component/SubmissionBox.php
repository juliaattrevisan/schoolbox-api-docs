<?php
/**
 * Model for submission boxes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component;
use Schoolbox;

class SubmissionBox {
	/**
	 * @var int
	 */
	protected $id;

	/**
	 * @var int
	 */
	protected $category;

	/**
	 * @var int
	 */
	protected $type;

	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @var string
	 */
	protected $description;

	/**
	 * @var int
	 */
	protected $legacy_file_id;

	/**
	 * @var SubmissionBoxFile
	 */
	protected $file_link;

	/**
	 * @var int
	 */
	protected $owner;

	/**
	 * Eventually, will accept DateTime fields.
	 *
	 * @var string
	 */
	protected $open_date;

	/**
	 * @var string
	 */
	protected $due_date;

	/**
	 * @var string
	 */
	protected $late_date;

	/**
	 * @var bool
	 */
	protected $accept_online;

	/**
	 * @var bool
	 */
	protected $accept_multiple;

	/**
	 * @var string
	 */
	protected $created;

	/**
	 * @var int
	 */
	protected $max_mark;

	/**
	 * @var int
	 */
	protected $mark_type;

	/**
	 * @var int
	 */
	protected $rubric_result_set;

	/**
	 * @param int $category
	 */
	public function setCategory($category) {
		$this->category = $category;
	}

	/**
	 * @return int
	 */
	public function getCategory() {
		return $this->category;
	}

	/**
	 * @param string $description
	 */
	public function setDescription($description) {
		$this->description = $description;
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
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
	 * @param int $legacy_file_id
	 */
	public function setLegacyFileId($legacy_file_id) {
		$this->legacy_file_id = $legacy_file_id;
	}

	/**
	 * @return int
	 */
	public function getLegacyFileId() {
		return $this->legacy_file_id;
	}

	/**
	 * @param string $name
	 */
	public function setName($name) {
		$this->name = $name;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @param int $type
	 */
	public function setType($type) {
		$this->type = $type;
	}

	/**
	 * @return int
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @param SubmissionBoxFile $link
	 */
	public function setFileLink(SubmissionBoxFile $link) {
		$this->file_link = $link;
	}

	/**
	 * @return SubmissionBoxFile
	 */
	public function getFileLink() {
		return $this->file_link;
	}

	/**
	 * @param boolean $accept_multiple
	 */
	public function setAcceptMultiple($accept_multiple) {
		$this->accept_multiple = $accept_multiple;
	}

	/**
	 * @return boolean
	 */
	public function getAcceptMultiple() {
		return $this->accept_multiple;
	}

	/**
	 * @param boolean $accept_online
	 */
	public function setAcceptOnline($accept_online) {
		$this->accept_online = $accept_online;
	}

	/**
	 * @return boolean
	 */
	public function getAcceptOnline() {
		return $this->accept_online;
	}

	/**
	 * @param string $created
	 */
	public function setCreated($created) {
		$this->created = $created;
	}

	/**
	 * @return string
	 */
	public function getCreated() {
		return $this->created;
	}

	/**
	 * @param string $due_date
	 */
	public function setDueDate($due_date) {
		$this->due_date = $due_date;
	}

	/**
	 * @return string
	 */
	public function getDueDate() {
		return $this->due_date;
	}

	/**
	 * @param string $late_date
	 */
	public function setLateDate($late_date) {
		$this->late_date = $late_date;
	}

	/**
	 * @return string
	 */
	public function getLateDate() {
		return $this->late_date;
	}

	/**
	 * @param int $mark_type
	 */
	public function setMarkType($mark_type) {
		$this->mark_type = $mark_type;
	}

	/**
	 * @return int
	 */
	public function getMarkType() {
		return $this->mark_type;
	}

	/**
	 * @param int $max_mark
	 */
	public function setMaxMark($max_mark) {
		$this->max_mark = $max_mark;
	}

	/**
	 * @return int
	 */
	public function getMaxMark() {
		return $this->max_mark;
	}

	/**
	 * @param string $open_date
	 */
	public function setOpenDate($open_date) {
		$this->open_date = $open_date;
	}

	/**
	 * @return string
	 */
	public function getOpenDate() {
		return $this->open_date;
	}

	/**
	 * @param int $owner
	 */
	public function setOwner($owner) {
		$this->owner = $owner;
	}

	/**
	 * @return int
	 */
	public function getOwner() {
		return $this->owner;
	}

	/**
	 * @param int $rubric_result_set
	 */
	public function setRubricResultSet($rubric_result_set) {
		$this->rubric_result_set = $rubric_result_set;
	}

	/**
	 * @return int
	 */
	public function getRubricResultSet() {
		return $this->rubric_result_set;
	}

}
