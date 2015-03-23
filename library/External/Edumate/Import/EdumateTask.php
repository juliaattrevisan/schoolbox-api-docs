<?php
/**
 * EdumateTask
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace External\Edumate\Import;

class EdumateTask {

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
	 * The designated main teacher of a particular class code
	 *
	 * @var null
	 */
	private $staffNumber = null;

	/**
	 * The name of the task
	 *
	 * @var string
	 */
	private $task = '';

	/**
	 * The type of the task (this is actually going to be "Task from Edumate"
	 * regardless of its value)
	 *
	 * @var string
	 */
	private $taskType = '';

	/**
	 * A description of the task (unused)
	 *
	 * @var string
	 */
	private $description = '';

	/**
	 * Synonymous with Open Date (and publish date)
	 *
	 * @var string
	 */
	private $setDate = '';

	/**
	 * Synonymous with Due Date
	 *
	 * @var string
	 */
	private $dueDate = '';

	/**
	 * Synonymous with Late Date
	 *
	 * @var string
	 */
	private $closeDate = '';

	/**
	 * Whether or not to accept submissions
	 *
	 * @var null
	 */
	private $acceptSubmissions = null;

	/**
	 * Mark Type. Edumate Tasks are usually considered as simple Raw marks
	 *
	 * @var string
	 */
	private $markType = '';

	/**
	 * Mark visibility (unused?)
	 *
	 * @var string
	 */
	private $markVisibility = '';

	/**
	 * Mark weighting (a percentage)
	 *
	 * @var null
	 */
	private $weighting = null;

	/**
	 * Total marks available
	 *
	 * @var null
	 */
	private $totalMark = null;

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
		$this->setStaffNumber($row['STAFF_NUMBER']);
		$this->setTask($row['TASK']);
		$this->setTaskType($row['TASK_TYPE']);
		$this->setDescription((string)$row['DESCRIPTION']);
		$this->setSetDate($row['SET_DATE']);
		$this->setDueDate($row['DUE_DATE']);
		$this->setCloseDate($row['CLOSE_DATE']);
		//$this->setAcceptSubmissions($row['ACCEPT_SUBMISSIONS']);
		$this->setMarkType($row['MARK_TYPE']);
		$this->setMarkVisibility($row['MARK_VISIBILITY']);
		$this->setWeighting($row['WEIGHTING'] ? $row['WEIGHTING'] : 0 );
		$this->setTotalMark($row['TOTAL_MARK']);
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
	 * Set accept submissions
	 *
	 * @param null $acceptSubmissions
	 */
	public function setAcceptSubmissions($acceptSubmissions) {
		$this->acceptSubmissions = $acceptSubmissions;
	}

	/**
	 * Get accept submissions
	 *
	 * @return null
	 */
	public function getAcceptSubmissions() {
		return $this->acceptSubmissions;
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
	 * Set close date
	 *
	 * @param string $closeDate
	 */
	public function setCloseDate($closeDate) {
		$this->closeDate = $closeDate;
	}

	/**
	 * Get close date
	 *
	 * @return string
	 */
	public function getCloseDate() {
		return $this->closeDate;
	}

	/**
	 * Set description
	 *
	 * @param string $description
	 */
	public function setDescription($description) {
		$this->description = $description;
	}

	/**
	 * Get description
	 *
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 * Set due date
	 *
	 * @param string $dueDate
	 */
	public function setDueDate($dueDate) {
		$this->dueDate = $dueDate;
	}

	/**
	 * Get due date
	 *
	 * @return string
	 */
	public function getDueDate() {
		return $this->dueDate;
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
	 * Set mark type
	 *
	 * @param string $markType
	 */
	public function setMarkType($markType) {
		$this->markType = $markType;
	}

	/**
	 * Get mark type
	 *
	 * @return string
	 */
	public function getMarkType() {
		return $this->markType;
	}

	/**
	 * Set mark visibility
	 *
	 * @param string $markVisibility
	 */
	public function setMarkVisibility($markVisibility) {
		$this->markVisibility = $markVisibility;
	}

	/**
	 * Get mark visibility
	 *
	 * @return string
	 */
	public function getMarkVisibility() {
		return $this->markVisibility;
	}

	/**
	 * Set set date
	 *
	 * @param string $setDate
	 */
	public function setSetDate($setDate) {
		$this->setDate = $setDate;
	}

	/**
	 * Get set date
	 *
	 * @return string
	 */
	public function getSetDate() {
		return $this->setDate;
	}

	/**
	 * Set staff number
	 *
	 * @param null $staffNumber
	 */
	public function setStaffNumber($staffNumber) {
		$this->staffNumber = $staffNumber;
	}

	/**
	 * Get staff number
	 *
	 * @return null
	 */
	public function getStaffNumber() {
		return $this->staffNumber;
	}

	/**
	 * Set task
	 *
	 * @param string $task
	 */
	public function setTask($task) {
		$this->task = $task;
	}

	/**
	 * Get task
	 *
	 * @return string
	 */
	public function getTask() {
		return $this->task;
	}

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
	 * Set task type
	 *
	 * @param string $taskType
	 */
	public function setTaskType($taskType) {
		$this->taskType = $taskType;
	}

	/**
	 * Get task type
	 *
	 * @return string
	 */
	public function getTaskType() {
		return $this->taskType;
	}

	/**
	 * Set total mark
	 *
	 * @param null $totalMark
	 */
	public function setTotalMark($totalMark) {
		$this->totalMark = $totalMark;
	}

	/**
	 * Get total mark
	 *
	 * @return null
	 */
	public function getTotalMark() {
		return $this->totalMark;
	}

	/**
	 * Set weighting
	 *
	 * @param null $weighting
	 */
	public function setWeighting($weighting) {
		$this->weighting = $weighting;
	}

	/**
	 * Get weighting
	 *
	 * @return null
	 */
	public function getWeighting() {
		return $this->weighting;
	}
}