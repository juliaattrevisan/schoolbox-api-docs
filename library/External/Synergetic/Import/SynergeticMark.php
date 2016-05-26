<?php
/**
 * SynergeticMark
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace External\Synergetic\Import;


class SynergeticMark {

	/**
	 * Task ID from Synergetic. A task seems able to be assigned to a number
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
	 * Constructor
	 * Optionally hydrate if an Synergetic array is provided
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
		$this->setStudentId($row['STUDENT_ID']);
		$this->setMark($row['MARK']);
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