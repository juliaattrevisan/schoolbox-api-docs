<?php
/**
 * Visibility
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Assess;

class Visibility {

	/**
	 * Identifier
	 *
	 * @var null
	 */
	private $id = null;

	/**
	 * Name of the visibility status
	 *
	 * @var string
	 */
	private $name = '';

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 * Is this Quiz mark visible to staff only
	 *
	 * @return bool
	 */
	public function isStaff() {
		if ($this->getId() == \Schoolbox\Assessment\Component\SubmissionBox::MARK_VISIBILITY_PRIVATE) {
			return true;
		}

		return false;
	}

	/**
	 * Is this Quiz mark visible to staff and students
	 *
	 * @return bool
	 */
	public function isStaffStudent() {
		if ($this->getId() == \Schoolbox\Assessment\Component\SubmissionBox::MARK_VISIBILITY_STUDENT) {
			return true;
		}

		return false;
	}

	/**
	 * Is this Quiz mark visible to staff and students
	 *
	 * @return bool
	 */
	public function isStaffStudentParent() {
		if ($this->getId() == \Schoolbox\Assessment\Component\SubmissionBox::MARK_VISIBILITY_PARENT_STUDENT) {
			return true;
		}

		return false;
	}

	/* Getters/Setters
	 */

	/**
	 * Set id
	 *
	 * @param null $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * Get id
	 *
	 * @return null
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Set name
	 *
	 * @param string $name
	 */
	public function setName($name) {
		$this->name = $name;
	}

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}
}