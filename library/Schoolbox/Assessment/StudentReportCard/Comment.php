<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\StudentReportCard;


use Schoolbox\Resource\Category;
use Schoolbox\User;

class Comment {

	/** @var int $id */
	protected $id;

	/** @var User $commenter */
	protected $commenter;

	/** @var User $student */
	protected $student;

	/** @var Category $category */
	protected $category;

	/** @var string $body */
	protected $body;

	/** @var \Alaress_Date_Format $created */
	protected $created;

	/** @var \Alaress_Date_Format $modified */
	protected $modified;

	/** @var \Alaress_Date_Format $deleted */
	protected $deleted;

	/** @var bool $parent_visibility */
	protected $parent_visibility;

	/** @var bool $student_visibility */
	protected $student_visibility;


	public function __construct() {
		$this->created = new \Alaress_Date_Format();
	}


	/**
	 * @param string $body
	 */
	public function setBody($body) {
		$this->body = $body;
	}

	/**
	 * @return string
	 */
	public function getBody() {
		return $this->body;
	}

	/**
	 * @param \Schoolbox\Resource\Category $category
	 */
	public function setCategory($category) {
		$this->category = $category;
	}

	/**
	 * @return \Schoolbox\Resource\Category
	 */
	public function getCategory() {
		return $this->category;
	}

	/**
	 * @param \Schoolbox\User $commenter
	 */
	public function setCommenter($commenter) {
		$this->commenter = $commenter;
	}

	/**
	 * @return \Schoolbox\User
	 */
	public function getCommenter() {
		return $this->commenter;
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
	 * @param \Alaress_Date_Format $deleted_date
	 */
	public function setDeleted($deleted_date) {
		$this->deleted = $deleted_date;
	}

	/**
	 * @return \Alaress_Date_Format
	 */
	public function getDeleted() {
		return $this->deleted;
	}

	/**
	 * @param \Alaress_Date_Format $modified_date
	 */
	public function setModified($modified_date) {
		$this->modified = $modified_date;
	}

	/**
	 * @return \Alaress_Date_Format
	 */
	public function getModified() {
		return $this->modified;
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
	 * @param boolean $parent_visibility
	 */
	public function setParentVisibility($parent_visibility) {
		$this->parent_visibility = $parent_visibility;
	}

	/**
	 * @return boolean
	 */
	public function getParentVisibility() {
		return $this->parent_visibility;
	}

	/**
	 * @return boolean
	 */
	public function isVisibleToParent() {
		return !!$this->parent_visibility;
	}

	/**
	 * @param \Schoolbox\User $student
	 */
	public function setStudent($student) {
		$this->student = $student;
	}

	/**
	 * @return \Schoolbox\User
	 */
	public function getStudent() {
		return $this->student;
	}

	/**
	 * @param boolean $student_visibility
	 */
	public function setStudentVisibility($student_visibility) {
		$this->student_visibility = $student_visibility;
	}

	/**
	 * @return boolean
	 */
	public function getStudentVisibility() {
		return $this->student_visibility;
	}

	/**
	 * @return boolean
	 */
	public function isVisibleToStudent() {
		return !!$this->student_visibility;
	}

	/**
	 * @return boolean
	 */
	public function isDeleted() {
		return !!$this->deleted;
	}

	/**
	 * Returns the most relevant of modified- and created-dates.
	 *
	 * @return \Alaress_Date_Format
	 */
	public function getRelevantDate() {
		if($this->modified) {
			return $this->modified;
		}
		return $this->created;
	}


}