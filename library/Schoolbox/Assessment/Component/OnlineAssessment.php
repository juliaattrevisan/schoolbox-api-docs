<?php
/**
 * Model for online assessments.
 *
 * @todo Add related models, such as question, answer, etc.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component;

use \Alaress_Date_Format;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\ISoftDeletable;
use Schoolbox\Resource\Category;
use Schoolbox\User;
use Schoolbox\Assessment\Component\SubmissionBox\WorkType;

class OnlineAssessment implements ISoftDeletable {

	const AVAILABILITY_ASSESS = 'assess';
	const AVAILABILITY_SURVEY = 'survey';

	/**
	 * @var int
	 */
	protected $id;

	/**
	 * @var Category
	 */
	protected $category;

	/**
	 * @var User
	 */
	protected $user;

	/**
	 * Not strictly required, because it's always WorkType::TYPE_ONLINE_ASSESSMENT.
	 *
	 * @var WorkType
	 */
	protected $work_type;

	/**
	 * @var string
	 */
	protected $title;

	/**
	 * @var Task
	 */
	protected $task;

	/**
	 * @var string
	 */
	protected $description;

	/**
	 * @var Alaress_Date_Format
	 */
	protected $start;

	/**
	 * @var Alaress_Date_Format
	 */
	protected $finish;

	/**
	 * @var int
	 */
	protected $duration;

	/**
	 * @var int
	 */
	protected $max_attempts;

	/**
	 * @var string
	 */
	protected $availability = self::AVAILABILITY_ASSESS;

	/**
	 * @var bool
	 */
	protected $anonymous_result;

	/**
	 * @var bool
	 */
	protected $notify_author;

	/**
	 * @var Alaress_Date_Format
	 */
	protected $created;

	/**
	 * @var Alaress_Date_Format
	 */
	protected $deleted;

	/**
	 * @param  boolean $anonymous_result
	 *
	 * @return $this
	 */
	public function setAnonymousResult($anonymous_result) {
		$this->anonymous_result = $anonymous_result;
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getAnonymousResult() {
		return $this->anonymous_result;
	}

	/**
	 * @param  string $availability
	 *
	 * @return $this
	 */
	public function setAvailability($availability) {
		$this->availability = $availability;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getAvailability() {
		return $this->availability;
	}

	/**
	 * @param  \Schoolbox\Resource\Category $category
	 *
	 * @return $this
	 */
	public function setCategory($category) {
		$this->category = $category;
		return $this;
	}

	/**
	 * @param  \Schoolbox\Assessment\Course\Task $task
	 * @return $this
	 */
	public function setTask($task) {
		$this->task = $task;
		return $this;
	}

	/**
	 * @return \Schoolbox\Assessment\Course\Task
	 */
	public function getTask() {
		return $this->task;
	}

	/**
	 * @return \Schoolbox\Resource\Category
	 */
	public function getCategory() {
		return $this->category;
	}

	/**
	 * @param  \Alaress_Date_Format $created
	 *
	 * @return $this
	 */
	public function setCreated($created) {
		$this->created = $created;
		return $this;
	}

	/**
	 * @return \Alaress_Date_Format
	 */
	public function getCreated() {
		return $this->created;
	}

	/**
	 * @param  string $description
	 *
	 * @return $this
	 */
	public function setDescription($description) {
		$this->description = $description;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 * @param  int $duration
	 *
	 * @return $this
	 */
	public function setDuration($duration) {
		$this->duration = $duration;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getDuration() {
		return $this->duration;
	}

	/**
	 * @param  \Alaress_Date_Format $finish
	 *
	 * @return $this
	 */
	public function setFinish($finish) {
		$this->finish = $finish;
		return $this;
	}

	/**
	 * @return \Alaress_Date_Format
	 */
	public function getFinish() {
		return $this->finish;
	}

	/**
	 * @param  int $id
	 *
	 * @return $this
	 */
	public function setId($id) {
		$this->id = $id;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @param  int $max_attempts
	 *
	 * @return $this
	 */
	public function setMaxAttempts($max_attempts) {
		$this->max_attempts = $max_attempts;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getMaxAttempts() {
		return $this->max_attempts;
	}

	/**
	 * @param  boolean $notify_author
	 *
	 * @return $this
	 */
	public function setNotifyAuthor($notify_author) {
		$this->notify_author = $notify_author;
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getNotifyAuthor() {
		return $this->notify_author;
	}

	/**
	 * @param  \Alaress_Date_Format $start
	 *
	 * @return $this
	 */
	public function setStart($start) {
		$this->start = $start;
		return $this;
	}

	/**
	 * @return \Alaress_Date_Format
	 */
	public function getStart() {
		return $this->start;
	}

	/**
	 * @param  string $title
	 *
	 * @return $this
	 */
	public function setTitle($title) {
		$this->title = $title;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getTitle() {
		return $this->title;
	}

	/**
	 * @param  \Schoolbox\User $user
	 *
	 * @return $this
	 */
	public function setUser($user) {
		$this->user = $user;
		return $this;
	}

	/**
	 * @return \Schoolbox\User
	 */
	public function getUser() {
		return $this->user;
	}

	/**
	 * @param  \Schoolbox\Assessment\Component\SubmissionBox\WorkType $work_type
	 *
	 * @return $this
	 */
	public function setWorkType($work_type) {
		$this->work_type = $work_type;
		return $this;
	}

	/**
	 * @return \Schoolbox\Assessment\Component\SubmissionBox\WorkType
	 */
	public function getWorkType() {
		return $this->work_type;
	}

	/**
	 * @param  \Alaress_Date_Format $deleted
	 * @return $this
	 */
	public function setDeleted($deleted) {
		$this->deleted = $deleted;
		return $this;
	}

	/**
	 * @return \Alaress_Date_Format
	 */
	public function getDeleted() {
		return $this->deleted;
	}

	/**
	 * Is this deleted? (added so there's a consistent method for determining if
	 * a model is deleted)
	 *
	 * @return bool
	 */
	public function isDeleted() {
		return ($this->getDeleted() instanceof \Alaress_Date_Format);
	}

}
