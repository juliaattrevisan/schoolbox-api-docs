<?php
/**
 * EdumateMark
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace External\Edumate\Import;


class EdumateMark {

	/**
	 * Task ID from Edumate. A task seems able to be assigned to a number
	 * of class codes, which contain numerous students.
	 *
	 * @var null
	 */
	private $taskId = null;

	/**
	 * A class code
	 *
	 * @var string
	 */
	private $classCode = '';

	/**
	 * The student in the $classCode who has marks for $taskId
	 *
	 * @var null
	 */
	private $studentId = null;

	/**
	 * The mark for the student
	 *
	 * @var string
	 */
	private $mark = '';

	/**
	 * Last updated datetime.microtime
	 * e.g. 2014-03-14 13:14:15.079771
	 *
	 * @var string
	 */
	private $lastUpdated = '';

	/**
	 * Constructor
	 * Optionally hydrate if an Edumate array is provided
	 *
	 * @param null $row
	 */
	public function __construct($row = null) {
		if (is_array($row)) {
			$this->hydrate($row);
		}
	}

	/**
	 * Populate this instance with array data, standardising content
	 * across the board
	 *
	 * @param $row
	 */
	public function hydrate($row) {
		$this->setTaskId($row['TASK_ID']);
		$this->setClassCode($row['CLASS_CODE']);
		$this->setStudentId($row['STUDENT_NUMBER']);
		$this->setMark($row['MARK']);
		$this->setLastUpdated($row['LAST_UPDATED']);
	}

	/**
	 * Last Updated is supplied with microtime, so blow that bit
	 * away and only use the datetime part
	 *
	 * @return mixed
	 */
	public function getLastUpdatedDate() {
		$bits = explode('.', $this->getLastUpdated());

		return $bits[0];
	}

	/* Getters/Setters
	 */

	/**
	 * Set task id
	 *
	 * @param null $taskId
	 */
	public function setTaskId($taskId) {
		$this->taskId = $taskId;
	}

	/**
	 * Get task id
	 *
	 * @return null
	 */
	public function getTaskId() {
		return $this->taskId;
	}

	/**
	 * Set class code
	 *
	 * @param string $classCode
	 */
	public function setClassCode($classCode) {
		$this->classCode = $classCode;
	}

	/**
	 * Get class code
	 *
	 * @return string
	 */
	public function getClassCode() {
		return $this->classCode;
	}

	/**
	 * Set last updated
	 *
	 * @param string $lastUpdated
	 */
	public function setLastUpdated($lastUpdated) {
		$this->lastUpdated = $lastUpdated;
	}

	/**
	 * Get last updated
	 *
	 * @return string
	 */
	public function getLastUpdated() {
		return $this->lastUpdated;
	}

	/**
	 * Set mark
	 *
	 * @param string $mark
	 */
	public function setMark($mark) {
		$this->mark = $mark;
	}

	/**
	 * Get mark
	 *
	 * @return string
	 */
	public function getMark() {
		return $this->mark;
	}

	/**
	 * Set student id
	 *
	 * @param null $studentId
	 */
	public function setStudentId($studentId) {
		$this->studentId = $studentId;
	}

	/**
	 * Get student id
	 *
	 * @return null
	 */
	public function getStudentId() {
		return $this->studentId;
	}
}