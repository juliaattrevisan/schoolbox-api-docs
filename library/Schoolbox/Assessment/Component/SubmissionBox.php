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

use Schoolbox\Assessment\Component\SubmissionBox\File;
use Schoolbox\Assessment\Component\SubmissionBox\FileCollection;
use Schoolbox\Assessment\Component\SubmissionBox\Notification;
use Schoolbox\Assessment\Component\SubmissionBox\NotificationCollection;

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
	protected $work_type;

	/**
	 * @var string
	 */
	protected $work_type_name;

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
	 * @var FileCollection
	 */
	protected $file_links;

	/**
	 * @var NotificationCollection
	 */
	protected $notifications;

	/**
	 * @var int
	 */
	protected $owner;

	/**
	 * @var \Alaress_Date_Format
	 */
	protected $open_date;

	/**
	 * @var \Alaress_Date_Format
	 */
	protected $due_date;

	/**
	 * @var \Alaress_Date_Format
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
	 * @var \Alaress_Date_Format
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
	 * @var string
	 */
	protected $mark_type_name;

	/**
	 * @var int
	 */
	protected $rubric_result_set;

	/**
	 * Temporary variable for storing category name.
	 * TODO create category mapper and load the category into the submission.
	 *
	 * @var string
	 */
	protected $category_name;

	/**
	 * Temporary variable for storing category codes for the category.
	 *
	 * @var array
	 */
	protected $category_codes;

	public function __construct() {
		$this->setNotifications(new NotificationCollection(array()));
		$this->setFileLinks(new SubmissionBox\FileCollection(array()));
	}

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
	public function setWorkType($type) {
		$this->work_type = $type;
	}

	/**
	 * @return int
	 */
	public function getWorkType() {
		return $this->work_type;
	}

	/**
	 * @param FileCollection $link
	 */
	public function setFileLinks($link) {
		$this->file_links = $link;
	}

	/**
	 * @return FileCollection
	 */
	public function getFileLinks() {
		return $this->file_links;
	}

	/**
	 * @param File $file
	 */
	public function addFileLink(File $file) {
		$this->file_links->add($file);
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
	 * @param \Alaress_Date_Format $created
	 */
	public function setCreated($created) {
		$this->created = $created;
	}

	/**
	 * @return \Alaress_Date_Format
	 */
	public function getCreated() {
		return $this->created;
	}

	/**
	 * @param \Alaress_Date_Format $due_date
	 */
	public function setDueDate($due_date) {
		$this->due_date = $due_date;
	}

	/**
	 * @return \Alaress_Date_Format
	 */
	public function getDueDate() {
		return $this->due_date;
	}

	/**
	 * @param \Alaress_Date_Format $late_date
	 */
	public function setLateDate($late_date) {
		$this->late_date = $late_date;
	}

	/**
	 * @return \Alaress_Date_Format
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
	 * @param \Alaress_Date_Format $open_date
	 */
	public function setOpenDate($open_date) {
		$this->open_date = $open_date;
	}

	/**
	 * @return \Alaress_Date_Format
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

	/**
	 * @param NotificationCollection $notifications
	 */
	public function setNotifications($notifications) {
		$this->notifications = $notifications;
	}

	/**
	 * @return NotificationCollection
	 */
	public function getNotifications() {
		return $this->notifications;
	}

	/**
	 * @param Notification $notification
	 */
	public function addNotification(Notification $notification) {
		$this->notifications->add($notification);
	}

	/**
	 * @param array $category_codes
	 */
	public function setCategoryCodes($category_codes) {
		$this->category_codes = $category_codes;
	}

	/**
	 * @return array
	 */
	public function getCategoryCodes() {
		return $this->category_codes;
	}

	/**
	 * @param string $category_name
	 */
	public function setCategoryName($category_name) {
		$this->category_name = $category_name;
	}

	/**
	 * @return string
	 */
	public function getCategoryName() {
		return $this->category_name;
	}

	/**
	 * @param string $mark_type_name
	 */
	public function setMarkTypeName($mark_type_name) {
		$this->mark_type_name = $mark_type_name;
	}

	/**
	 * @return string
	 */
	public function getMarkTypeName() {
		return $this->mark_type_name;
	}

	/**
	 * @param string $work_type_name
	 */
	public function setWorkTypeName($work_type_name) {
		$this->work_type_name = $work_type_name;
	}

	/**
	 * @return string
	 */
	public function getWorkTypeName() {
		return $this->work_type_name;
	}
}
